<x-app-layout title="Receive Purchase Order">
    @php
        $supplierAddress = collect([
            $purchase->supplier?->address_line_1,
            $purchase->supplier?->address_line_2,
            $purchase->supplier?->city,
            $purchase->supplier?->state,
            $purchase->supplier?->postal_code,
            $purchase->supplier?->country,
        ])->filter()->join(', ') ?: ($purchase->supplier?->address ?: '-');
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Receive Purchase Order') }} #{{ $purchase->invoice_number ?: $purchase->id }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">Confirm received quantities, proof image, and final receipt details.</p>
            </div>
            <a href="{{ route('purchases.show', $purchase) }}" class="inline-flex h-8 items-center justify-center gap-1.5 rounded border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                {{ __('Back') }}
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form
                action="{{ route('purchases.mark-received', $purchase) }}"
                method="POST"
                enctype="multipart/form-data"
                x-data="{
                    submitting: false,
                    priceModal: {
                        index: null,
                        product: '',
                        sku: '',
                        value: 0,
                        discount: 0,
                    },
                    items: [
                        @foreach($purchase->items as $item)
                            @php
                                $initialMrp = (float) ($item->selling_price ?: $item->unit_price);
                                $initialDiscount = $initialMrp > 0 ? (1 - ((float) $item->unit_price / $initialMrp)) * 100 : 0;
                            @endphp
                            {
                                id: {{ $item->id }},
                                product: @js($item->product->name ?? '-'),
                                sku: @js($item->product->sku ?? $item->product->code ?? ''),
                                ordered: {{ (int) $item->quantity }},
                                received: {{ (int) old('items.'.$item->id.'.received_quantity', $item->received_quantity ?? $item->quantity) }},
                                unitPrice: {{ number_format((float) $item->unit_price, 2, '.', '') }},
                                mrp: {{ $initialMrp }},
                                productMrp: {{ number_format((float) old('items.'.$item->id.'.product_mrp', $item->product?->mrp ?? ($item->selling_price ?: $item->unit_price)), 2, '.', '') }},
                                discount: {{ number_format($initialDiscount, 6, '.', '') }},
                            },
                        @endforeach
                    ],
                    vendorAdditionalDiscount: {{ Js::from(old('vendor_additional_discount', 0)) }},
                    openPriceModal(index) {
                        this.priceModal = {
                            index,
                            product: this.items[index].product,
                            sku: this.items[index].sku,
                            value: this.items[index].productMrp,
                            discount: this.items[index].discount,
                        };
                        this.$dispatch('open-modal', { name: 'receive-product-price-modal' });
                    },
                    updateProductPrice() {
                        if (this.priceModal.index !== null) {
                            const item = this.items[this.priceModal.index];
                            const newMrp = parseFloat(this.priceModal.value) || 0;
                            const discount = parseFloat(item.discount) || 0;

                            item.productMrp = Number(newMrp.toFixed(2));
                            item.mrp = Number(newMrp.toFixed(2));
                            item.unitPrice = Number((newMrp * (1 - (discount / 100))).toFixed(2));
                        }
                        this.$dispatch('close-modal', { name: 'receive-product-price-modal' });
                    },
                    lineTotal(item) {
                        return Number(((parseInt(item.received) || 0) * (parseFloat(item.unitPrice) || 0)).toFixed(2));
                    },
                    discountPercent(item) {
                        return (parseFloat(item.discount) || 0).toFixed(2) + '%';
                    },
                    get total() {
                        return Number(this.items.reduce((sum, item) => sum + this.lineTotal(item), 0).toFixed(2));
                    },
                    get vendorAdditionalAdjustment() {
                        return Number((parseFloat(this.vendorAdditionalDiscount) || 0).toFixed(2));
                    },
                    get finalInvoiceValue() {
                        return Number(Math.max(0, this.total - this.vendorAdditionalAdjustment).toFixed(2));
                    }
                }"
                @submit="submitting = true"
                class="space-y-6"
            >
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 md:grid-cols-4">
                    <div>
                        <div class="text-xs font-medium uppercase text-gray-500">PO Reference</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $purchase->invoice_number ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase text-gray-500">Supplier / Vendor</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $purchase->supplier->name ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase text-gray-500">PO Date</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $purchase->purchase_date?->format('d/m/Y') }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase text-gray-500">Required Delivery Date</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ $purchase->due_date?->format('d/m/Y') ?: '-' }}</div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Order From / Supplier') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Supplier details for receiving this purchase order.') }}</p>
                    <div class="mt-4 grid grid-cols-1 gap-3 text-sm text-slate-700 md:grid-cols-2 lg:grid-cols-3">
                        <p class="font-semibold text-gray-900">{{ $purchase->supplier->name ?? '-' }}</p>
                        <p><span class="font-medium text-gray-500">{{ __('Contact') }}:</span> {{ $purchase->supplier->contact_person ?: '-' }}</p>
                        <p><span class="font-medium text-gray-500">{{ __('Phone') }}:</span> {{ format_indian_phone($purchase->supplier->phone) }}</p>
                        <p><span class="font-medium text-gray-500">{{ __('Email') }}:</span> {{ $purchase->supplier->email ?: '-' }}</p>
                        <p><span class="font-medium text-gray-500">{{ __('GST / Tax ID') }}:</span> {{ $purchase->supplier->tax_id ?: '-' }}</p>
                        <p class="md:col-span-2 lg:col-span-3"><span class="font-medium text-gray-500">{{ __('Address') }}:</span> {{ $supplierAddress }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-3">
                    @unless($purchase->invoice_number)
                        <div class="space-y-2">
                            <x-input-label for="invoice_number" :value="__('Final Invoice Number')" required hint="Supplier invoice number received with the goods. Example: INV-2026-001." />
                            <x-text-input id="invoice_number" name="invoice_number" :value="old('invoice_number')" required placeholder="INV...." />
                            <x-input-error :messages="$errors->get('invoice_number')" class="mt-2" />
                        </div>
                    @endunless

                    <div class="space-y-2">
                        <x-input-label for="proof_image" :value="__('Proof Receipt Image')" hint="Optional image proof for received goods or supplier invoice. Example: invoice-photo.jpg." />
                        <input
                            id="proof_image"
                            type="file"
                            name="proof_image"
                            accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        @if($purchase->proof_image)
                            <a href="{{ public_storage_url($purchase->proof_image) }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:underline">
                                <x-heroicon-o-paper-clip class="h-4 w-4" />
                                View current proof image
                            </a>
                        @else
                            <p class="text-xs text-gray-500">Optional image (JPG, PNG) max 2MB.</p>
                        @endif
                        <x-input-error :messages="$errors->get('proof_image')" class="mt-2" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="vendor_invoice_number" :value="__('Vendor Invoice Number')" hint="Invoice number printed on the supplier invoice. Example: INV-2026-001." />
                        <x-text-input id="vendor_invoice_number" name="vendor_invoice_number" :value="old('vendor_invoice_number')" placeholder="INV...." />
                        <x-input-error :messages="$errors->get('vendor_invoice_number')" class="mt-2" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="order_received_date" :value="__('Order Received Date')" required hint="Date when goods were physically received. Example: today." />
                        <x-text-input id="order_received_date" type="date" name="order_received_date" :value="old('order_received_date', now()->toDateString())" required />
                        <x-input-error :messages="$errors->get('order_received_date')" class="mt-2" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="vendor_invoice_file" :value="__('Vendor Invoice File')" hint="Invoice document sent by supplier. Example: supplier-invoice.pdf." />
                        <input
                            id="vendor_invoice_file"
                            type="file"
                            name="vendor_invoice_file"
                            accept="application/pdf,image/jpeg,image/png,image/webp"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100"
                        />
                        <p class="text-xs text-gray-500">Optional PDF or image max 10MB.</p>
                        <x-input-error :messages="$errors->get('vendor_invoice_file')" class="mt-2" />
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Brand</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Ordered</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Received</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">MFG Date</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Expiry Date</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">MRP</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Discount %</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Price</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Received Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($purchase->items as $item)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-900">{{ $item->product->name ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">{{ $item->product->sku ?? $item->product->code ?? '' }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $item->product->company->short_name ?? $item->product->company->company_name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-center text-sm font-medium">{{ $item->quantity }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="number" name="items[{{ $item->id }}][received_quantity]" x-model.number="items[{{ $loop->index }}].received" min="0" class="w-24 rounded-md border-gray-300 text-center text-sm">
                                            <x-input-error :messages="$errors->get('items.'.$item->id.'.received_quantity')" class="mt-1" />
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="date" name="items[{{ $item->id }}][manufacturing_date]" value="{{ old('items.'.$item->id.'.manufacturing_date', $item->manufacturing_date?->format('Y-m-d')) }}" class="w-36 rounded-md border-gray-300 text-sm">
                                            <x-input-error :messages="$errors->get('items.'.$item->id.'.manufacturing_date')" class="mt-1" />
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="date" name="items[{{ $item->id }}][expiry_date]" value="{{ old('items.'.$item->id.'.expiry_date', $item->expiry_date?->format('Y-m-d')) }}" class="w-36 rounded-md border-gray-300 text-sm">
                                            <x-input-error :messages="$errors->get('items.'.$item->id.'.expiry_date')" class="mt-1" />
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold">
                                            <span x-text="window.formatMoney(items[{{ $loop->index }}].productMrp)"></span>
                                            <input type="hidden" name="items[{{ $item->id }}][product_mrp]" x-model="items[{{ $loop->index }}].productMrp">
                                            <input type="hidden" name="items[{{ $item->id }}][unit_price]" x-model="items[{{ $loop->index }}].unitPrice">
                                            <input type="hidden" name="items[{{ $item->id }}][selling_price]" x-model="items[{{ $loop->index }}].productMrp">
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm" x-text="discountPercent(items[{{ $loop->index }}])"></td>
                                        <td class="px-4 py-3 text-right text-sm" x-text="window.formatMoney(items[{{ $loop->index }}].unitPrice)"></td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <span class="text-sm font-semibold" x-text="window.formatMoney(lineTotal(items[{{ $loop->index }}]))"></span>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-amber-500 text-white hover:bg-amber-600"
                                                    title="{{ __('Update Product MRP') }}"
                                                    x-on:click="openPriceModal({{ $loop->index }})"
                                                >
                                                    <x-heroicon-o-currency-rupee class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="9" class="px-4 py-4 text-right font-bold">Total Received Value</td>
                                    <td class="px-4 py-4 text-right text-lg font-bold text-green-600" x-text="window.formatMoney(total)"></td>
                                </tr>
                                <tr>
                                    <td colspan="9" class="px-4 py-4 text-right font-bold">Vendor Additional Discount</td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="inline-flex w-48 items-center rounded-md border border-gray-300 bg-white focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                                            <span class="border-r border-gray-200 px-3 text-sm font-semibold text-gray-600">INR</span>
                                            <input
                                                type="number"
                                                name="vendor_additional_discount"
                                                x-model.number="vendorAdditionalDiscount"
                                                step="0.01"
                                                class="w-full border-0 bg-transparent text-right text-sm font-semibold focus:ring-0"
                                                placeholder="0.00"
                                            >
                                        </div>
                                        <x-input-error :messages="$errors->get('vendor_additional_discount')" class="mt-1" />
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="9" class="px-4 py-4 text-right font-bold">Final Invoice Value</td>
                                    <td class="px-4 py-4 text-right text-lg font-bold text-blue-600" x-text="window.formatMoney(finalInvoiceValue)"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <x-modal name="receive-product-price-modal" maxWidth="md" :close-on-outside="false" :close-on-escape="false">
                    <div class="space-y-5 p-6">
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ __('Update Product MRP') }}</h3>
                                <p class="mt-1 text-sm font-medium text-gray-700" x-text="priceModal.product"></p>
                                <p class="mt-0.5 text-xs text-gray-500" x-text="priceModal.sku"></p>
                                <p class="mt-2 inline-flex rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                    {{ __('Discount') }}: <span class="ml-1" x-text="(parseFloat(priceModal.discount) || 0).toFixed(2) + '%'"></span>
                                </p>
                            </div>
                            <button
                                type="button"
                                x-on:click="$dispatch('close-modal', { name: 'receive-product-price-modal' })"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50"
                            >
                                <x-heroicon-o-x-mark class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="space-y-2">
                            <x-input-label for="receive_product_mrp" :value="__('Product MRP')" hint="This updates the main product page MRP and records price history. Example: 1299." />
                            <div class="flex items-center gap-3">
                                <x-text-input id="receive_product_mrp" type="number" min="0" x-model="priceModal.value" class="text-lg font-bold" />
                                <span class="text-lg font-bold text-gray-900">{{ __('INR') }}</span>
                            </div>
                            <p class="text-xs font-semibold text-gray-600">
                                {{ __('Calculated Net Price') }}:
                                <span class="text-gray-900" x-text="window.formatMoney(Number(((parseFloat(priceModal.value) || 0) * (1 - ((parseFloat(priceModal.discount) || 0) / 100))).toFixed(2)))"></span>
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                            <button type="button" x-on:click="$dispatch('close-modal', { name: 'receive-product-price-modal' })" class="inline-flex h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                {{ __('Cancel') }}
                            </button>
                            <button
                                type="button"
                                x-on:click="updateProductPrice()"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold shadow-sm"
                                style="display:flex !important; visibility:visible !important; opacity:1 !important; background-color:#059669 !important; color:#ffffff !important; border:1px solid #047857 !important;"
                            >
                                <x-heroicon-o-check class="h-4 w-4" />
                                {{ __('Update Price') }}
                            </button>
                        </div>
                    </div>
                </x-modal>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6">
                    <a href="{{ route('purchases.show', $purchase) }}" class="inline-flex h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('Cancel') }}
                    </a>
                    <x-primary-button
                        class="!bg-green-600 hover:!bg-green-700 focus:!ring-green-500"
                        x-bind:class="submitting ? 'opacity-75 cursor-not-allowed' : ''"
                        x-bind:disabled="submitting"
                    >
                        <svg x-show="submitting" class="-ml-1 mr-2 h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Confirm Receipt') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
