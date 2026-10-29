@php
    $existingPaymentRef = '';
    if (preg_match('/Ref:\s*([^\|\n\r]+)/i', $sale->notes ?? '', $matches)) {
        $existingPaymentRef = trim($matches[1]);
    }
@endphp
<x-app-layout title="Edit Sale" header-max-width="max-w-7xl">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Edit Historical Sale') }} #{{ $sale->invoice_number ?: $sale->id }}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ __('Carefully adjust line items, payment methods, or customer details. Inventory and financial records will synchronize automatically.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-secondary-button href="{{ route('sales.show', $sale) }}">
                    &larr; {{ __('Cancel & Return') }}
                </x-secondary-button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800">
                    <div class="flex items-center gap-2 font-bold mb-1">
                        <x-heroicon-o-exclamation-circle class="w-5 h-5 text-red-600" />
                        {{ __('Please correct the following errors:') }}
                    </div>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                action="{{ route('sales.update', $sale) }}"
                method="POST"
                x-data="saleEditForm({
                    initialItems: {{ Js::from(old('items', $sale->items->map(function ($item) {
                        return [
                            'key' => Str::random(8),
                            'product_id' => $item->product_id,
                            'service_id' => $item->service_id,
                            'is_service' => $item->isService(),
                            'name' => $item->product?->name ?? $item->service?->name ?? 'Unknown Item',
                            'code' => $item->product?->product_code ?? $item->product?->sku ?? '',
                            'unit_price' => (float) $item->unit_price,
                            'quantity' => (int) $item->quantity,
                            'discount' => (float) $item->discount,
                            'subtotal' => (float) $item->subtotal,
                        ];
                    }))) }},
                    saleDate: {{ Js::from(old('sale_date', $sale->sale_date->format('Y-m-d'))) }},
                    paymentMethod: {{ Js::from(old('payment_method', $sale->payment_method->value)) }},
                    paymentRef: {{ Js::from(old('payment_ref', $existingPaymentRef)) }},
                    customerId: {{ Js::from(old('customer_id', $sale->customer_id)) }},
                    customerName: {{ Js::from($sale->customer?->name ?? 'Guest') }},
                    notes: {{ Js::from(old('notes', $sale->notes)) }},
                    reason: {{ Js::from(old('reason', '')) }},
                    cashReceived: {{ Js::from(old('cash_received', (float) $sale->cash_received)) }},
                    roundOff: {{ Js::from(old('round_off', $sale->round_off !== null ? (float) $sale->round_off : 0)) }},
                    billDiscount: {{ Js::from(old('bill_discount', 0)) }},
                    couponId: {{ Js::from(old('coupon_id', $sale->discount_coupon_id)) }},
                    couponDiscount: {{ Js::from((float) $sale->coupon_discount) }}
                })"
                class="space-y-6"
            >
                @csrf
                @method('PUT')

                <!-- Top Metadata Section -->
                <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700 border-b border-gray-100 pb-2">
                        {{ __('Sale Metadata & Audit Information') }}
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Invoice Number (Readonly) -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">{{ __('Invoice Number') }}</label>
                            <input
                                type="text"
                                readonly
                                value="{{ $sale->invoice_number }}"
                                class="w-full text-sm bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 text-gray-700 font-mono"
                            >
                        </div>

                        <!-- Sale Date -->
                        <div>
                            <label for="sale_date" class="block text-xs font-semibold text-gray-600 uppercase mb-1">{{ __('Sale Date') }} *</label>
                            <input
                                type="date"
                                id="sale_date"
                                name="sale_date"
                                x-model="saleDate"
                                required
                                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                        </div>

                        <!-- Customer -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">{{ __('Customer') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="hidden" name="customer_id" :value="customerId">
                                <input
                                    type="text"
                                    readonly
                                    :value="customerName"
                                    class="w-full text-sm bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-gray-700"
                                >
                            </div>
                        </div>

                        <!-- Payment Method Selection -->
                        <div>
                            <label for="payment_method" class="block text-xs font-semibold text-gray-600 uppercase mb-1">{{ __('Payment Method') }} *</label>
                            <select
                                id="payment_method"
                                name="payment_method"
                                x-model="paymentMethod"
                                @change="onPaymentMethodChange()"
                                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-800 font-semibold focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                @foreach(\App\Enums\PaymentMethod::cases() as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Reason for Edit -->
                    <div>
                        <label for="reason" class="block text-xs font-semibold text-gray-700 uppercase mb-1">
                            {{ __('Reason for Historical Edit') }} <span class="text-amber-600 font-normal">({{ __('Recorded in Audit Log') }})</span>
                        </label>
                        <input
                            type="text"
                            id="reason"
                            name="reason"
                            x-model="reason"
                            placeholder="{{ __('e.g., Customer requested price correction, switch payment method from Cash to UPI, adjusted quantity') }}"
                            class="w-full text-sm border border-amber-300 bg-amber-50/30 rounded-lg px-3 py-2 text-gray-800 focus:ring-amber-500 focus:border-amber-500"
                        >
                    </div>
                </div>

                <!-- Line Items Section -->
                <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700">{{ __('Sale Line Items') }}</h3>
                            <p class="text-xs text-gray-500">{{ __('Modify quantities, unit prices, or discounts. Stock balances will adjust upon saving.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Quick Add Product/Service Search Input -->
                            <div class="relative w-72">
                                <input
                                    type="text"
                                    x-model="itemSearchQuery"
                                    @input.debounce.300ms="searchItems()"
                                    placeholder="{{ __('Search product or service to add...') }}"
                                    class="w-full text-xs border border-gray-300 rounded-lg pl-8 pr-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-2.5 top-2" />

                                <!-- Search Dropdown Results -->
                                <div
                                    x-show="searchResults.length > 0"
                                    @click.outside="searchResults = []"
                                    class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                                    style="display: none;"
                                >
                                    <template x-for="result in searchResults" :key="result.type + '-' + result.id">
                                        <button
                                            type="button"
                                            @click="addItem(result)"
                                            class="w-full text-left px-3 py-2 text-xs hover:bg-indigo-50 flex items-center justify-between border-b border-gray-100 last:border-b-0"
                                        >
                                            <div>
                                                <span class="font-bold text-gray-800" x-text="result.name"></span>
                                                <span class="text-gray-400 text-[10px] ml-1" x-text="result.code ? '(' + result.code + ')' : ''"></span>
                                                <span
                                                    class="ml-1 text-[10px] px-1.5 py-0.2 rounded font-semibold uppercase"
                                                    :class="result.is_service ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700'"
                                                    x-text="result.is_service ? 'Service' : 'Product'"
                                                ></span>
                                            </div>
                                            <span class="font-mono font-bold text-gray-700" x-text="'₹' + parseFloat(result.selling_price || result.unit_price || 0).toFixed(2)"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-600 text-xs font-semibold uppercase">
                                <tr>
                                    <th class="py-2.5 px-3">{{ __('Item') }}</th>
                                    <th class="py-2.5 px-3 w-32">{{ __('Unit Price (₹)') }}</th>
                                    <th class="py-2.5 px-3 w-28">{{ __('Discount (₹)') }}</th>
                                    <th class="py-2.5 px-3 w-32 text-center">{{ __('Qty') }}</th>
                                    <th class="py-2.5 px-3 w-32 text-right">{{ __('Subtotal (₹)') }}</th>
                                    <th class="py-2.5 px-3 w-16 text-center">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(item, index) in items" :key="item.key">
                                    <tr class="hover:bg-gray-50/50">
                                        <!-- Item Info & Hidden Inputs -->
                                        <td class="py-2.5 px-3">
                                            <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                            <input type="hidden" :name="'items[' + index + '][service_id]'" :value="item.service_id">
                                            <div class="font-medium text-gray-900" x-text="item.name"></div>
                                            <div class="text-xs text-gray-400" x-text="item.code ? 'Code: ' + item.code : (item.is_service ? 'Service' : '')"></div>
                                        </td>

                                        <!-- Unit Price -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                :name="'items[' + index + '][unit_price]'"
                                                x-model.number="item.unit_price"
                                                @input="calculateRow(item)"
                                                class="w-full text-xs border border-gray-300 rounded px-2 py-1 text-right focus:ring-indigo-500 focus:border-indigo-500"
                                            >
                                        </td>

                                        <!-- Item Discount -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                :name="'items[' + index + '][discount]'"
                                                x-model.number="item.discount"
                                                @input="calculateRow(item)"
                                                class="w-full text-xs border border-gray-300 rounded px-2 py-1 text-right focus:ring-indigo-500 focus:border-indigo-500"
                                            >
                                        </td>

                                        <!-- Quantity -->
                                        <td class="py-2.5 px-3">
                                            <div class="flex items-center justify-center gap-1">
                                                <button
                                                    type="button"
                                                    @click="if (item.quantity > 1) { item.quantity--; calculateRow(item); }"
                                                    class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold flex items-center justify-center text-xs"
                                                >-</button>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    :name="'items[' + index + '][quantity]'"
                                                    x-model.number="item.quantity"
                                                    @input="calculateRow(item)"
                                                    class="w-16 text-xs text-center border border-gray-300 rounded py-1 focus:ring-indigo-500 focus:border-indigo-500"
                                                >
                                                <button
                                                    type="button"
                                                    @click="item.quantity++; calculateRow(item);"
                                                    class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold flex items-center justify-center text-xs"
                                                >+</button>
                                            </div>
                                        </td>

                                        <!-- Subtotal -->
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-800" x-text="formatMoney(item.subtotal)"></td>

                                        <!-- Remove Action -->
                                        <td class="py-2.5 px-3 text-center">
                                            <button
                                                type="button"
                                                @click="removeItem(index)"
                                                class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50"
                                                title="{{ __('Remove item') }}"
                                            >
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bottom Section: Payment Tender & Financial Summary -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left: Payment Tender & Notes -->
                    <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">
                                {{ __('Payment Method') }}
                            </h3>
                            <span class="text-xs text-gray-400 font-medium">Selected: <strong class="text-indigo-600 capitalize" x-text="paymentMethodLabel()"></strong></span>
                        </div>

                        <!-- 3-4 Payment Buttons: UPI / Cash / Card / Transfer -->
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                            <!-- UPI -->
                            <button
                                type="button"
                                @click="selectPaymentMethod('upi')"
                                class="flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg border-2 transition-all font-bold text-xs"
                                :class="paymentMethod === 'upi' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            >
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                <span>UPI</span>
                            </button>

                            <!-- Cash -->
                            <button
                                type="button"
                                @click="selectPaymentMethod('cash')"
                                class="flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg border-2 transition-all font-bold text-xs"
                                :class="paymentMethod === 'cash' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            >
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <span>Cash</span>
                            </button>

                            <!-- Card -->
                            <button
                                type="button"
                                @click="selectPaymentMethod('card')"
                                class="flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg border-2 transition-all font-bold text-xs"
                                :class="paymentMethod === 'card' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            >
                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                <span>Card</span>
                            </button>

                            <!-- Bank Transfer -->
                            <button
                                type="button"
                                @click="selectPaymentMethod('transfer')"
                                class="flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg border-2 transition-all font-bold text-xs"
                                :class="paymentMethod === 'transfer' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            >
                                <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                <span>Transfer</span>
                            </button>
                        </div>

                        <!-- Tender Details Box -->
                        <!-- If CASH -->
                        <div x-show="paymentMethod === 'cash'" class="p-3.5 rounded-lg bg-emerald-50/50 border border-emerald-200 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-emerald-900 uppercase">{{ __('Cash Payment Received') }}</span>
                                <button
                                    type="button"
                                    @click="cashReceived = total"
                                    class="text-xs font-semibold text-emerald-700 hover:text-emerald-900 underline"
                                >
                                    {{ __('Set Exact Amount') }}
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">{{ __('Cash Received (₹)') }}</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="cash_received"
                                        x-model.number="cashReceived"
                                        class="w-full text-sm font-bold border border-gray-300 rounded-lg px-3 py-2 text-right focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">{{ __('Change to Return (₹)') }}</label>
                                    <input
                                        type="text"
                                        readonly
                                        :value="formatMoney(calculateChange())"
                                        class="w-full text-sm font-bold bg-white border border-gray-200 rounded-lg px-3 py-2 text-right text-emerald-700 font-mono"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- If UPI -->
                        <div x-show="paymentMethod === 'upi'" class="bg-indigo-50/60 rounded-lg p-2.5 border border-indigo-100 space-y-1.5">
                            <div class="flex items-center gap-1.5 text-indigo-700 text-xs font-semibold">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>{{ __('Collect via QR Code / UPI App') }}</span>
                            </div>
                            <input
                                type="text"
                                name="payment_ref"
                                x-model="paymentRef"
                                placeholder="{{ __('UPI UTR / Reference ID (Optional)...') }}"
                                class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                            >
                        </div>

                        <!-- If CARD -->
                        <div x-show="paymentMethod === 'card'" class="bg-blue-50/60 rounded-lg p-2.5 border border-blue-100 space-y-1.5">
                            <div class="flex items-center gap-1.5 text-blue-700 text-xs font-semibold">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                <span>{{ __('Swipe / Tap on POS machine') }}</span>
                            </div>
                            <input
                                type="text"
                                name="payment_ref"
                                x-model="paymentRef"
                                placeholder="{{ __('Card 4 Digits / Auth Ref (Optional)...') }}"
                                class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                            >
                        </div>

                        <!-- If TRANSFER -->
                        <div x-show="paymentMethod === 'transfer'" class="bg-purple-50/60 rounded-lg p-2.5 border border-purple-100 space-y-1.5">
                            <div class="flex items-center gap-1.5 text-purple-700 text-xs font-semibold">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                <span>{{ __('Bank Transfer / NEFT / IMPS Reference') }}</span>
                            </div>
                            <input
                                type="text"
                                name="payment_ref"
                                x-model="paymentRef"
                                placeholder="{{ __('Bank Transfer Reference (Optional)...') }}"
                                class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                            >
                        </div>

                        <!-- Additional Notes -->
                        <div>
                            <label for="notes" class="block text-xs font-semibold text-gray-600 uppercase mb-1">{{ __('Order Notes') }}</label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                x-model="notes"
                                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Right: Summary Card -->
                    <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-6 space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700 border-b border-gray-100 pb-2">
                            {{ __('Financial Summary') }}
                        </h3>

                        <div class="space-y-2.5 text-sm">
                            <div class="flex justify-between text-gray-600">
                                <span>{{ __('Items Gross Subtotal') }}</span>
                                <span class="font-mono font-bold" x-text="formatMoney(grossSubtotal)"></span>
                            </div>

                            <template x-if="productItemDiscounts > 0">
                                <div class="flex justify-between text-red-600">
                                    <span>{{ __('Product Discount') }}</span>
                                    <span class="font-mono font-bold" x-text="'-' + formatMoney(productItemDiscounts)"></span>
                                </div>
                            </template>

                            <template x-if="serviceItemDiscounts > 0">
                                <div class="flex justify-between text-purple-600">
                                    <span>{{ __('Service Discount') }}</span>
                                    <span class="font-mono font-bold" x-text="'-' + formatMoney(serviceItemDiscounts)"></span>
                                </div>
                            </template>

                            <div class="flex justify-between text-gray-700">
                                <span>{{ __('Net Subtotal') }}</span>
                                <span class="font-mono font-bold" x-text="formatMoney(netSubtotal)"></span>
                            </div>

                            <div class="flex items-center justify-between text-gray-600">
                                <label for="round_off" class="text-xs uppercase font-semibold">{{ __('Round Off Adjustment') }}</label>
                                <div class="w-32">
                                    <input
                                        type="number"
                                        step="0.01"
                                        id="round_off"
                                        name="round_off"
                                        x-model.number="roundOff"
                                        class="w-full text-xs text-right border border-gray-300 rounded px-2 py-1 focus:ring-indigo-500"
                                    >
                                </div>
                            </div>

                            <div class="pt-3 border-t border-gray-200 flex justify-between items-center text-lg font-bold text-gray-900">
                                <span>{{ __('Final Total') }}</span>
                                <span class="font-mono text-2xl text-indigo-700" x-text="formatMoney(total)"></span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-gray-100">
                            <x-secondary-button href="{{ route('sales.show', $sale) }}">
                                {{ __('Cancel') }}
                            </x-secondary-button>
                            <x-primary-button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5">
                                <x-heroicon-o-check class="w-5 h-5 mr-1.5" />
                                {{ __('Save Historical Changes') }}
                            </x-primary-button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function saleEditForm(config) {
            return {
                items: config.initialItems || [],
                saleDate: config.saleDate,
                paymentMethod: config.paymentMethod,
                paymentRef: config.paymentRef || '',
                customerId: config.customerId,
                customerName: config.customerName,
                notes: config.notes || '',
                reason: config.reason || '',
                cashReceived: config.cashReceived || 0,
                roundOff: config.roundOff || 0,
                billDiscount: config.billDiscount || 0,
                couponId: config.couponId,
                couponDiscount: config.couponDiscount || 0,

                itemSearchQuery: '',
                searchResults: [],

                init() {
                    this.items.forEach(item => this.calculateRow(item));
                },

                calculateRow(item) {
                    const price = parseFloat(item.unit_price) || 0;
                    const discount = parseFloat(item.discount) || 0;
                    const qty = parseInt(item.quantity) || 1;
                    const finalPrice = Math.max(0, price - discount);
                    item.subtotal = parseFloat((finalPrice * qty).toFixed(2));
                },

                removeItem(index) {
                    if (this.items.length <= 1) {
                        alert('A sale must have at least one item.');
                        return;
                    }
                    this.items.splice(index, 1);
                },

                get grossSubtotal() {
                    return this.items.reduce((sum, item) => sum + ((parseFloat(item.unit_price) || 0) * (parseInt(item.quantity) || 0)), 0);
                },

                get productItemDiscounts() {
                    return this.items.reduce((sum, item) => sum + (item.service_id ? 0 : ((parseFloat(item.discount) || 0) * (parseInt(item.quantity) || 0))), 0);
                },

                get serviceItemDiscounts() {
                    return this.items.reduce((sum, item) => sum + (item.service_id ? ((parseFloat(item.discount) || 0) * (parseInt(item.quantity) || 0)) : 0), 0);
                },

                get totalItemDiscounts() {
                    return this.items.reduce((sum, item) => sum + ((parseFloat(item.discount) || 0) * (parseInt(item.quantity) || 0)), 0);
                },

                get netSubtotal() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
                },

                get total() {
                    const net = this.netSubtotal;
                    const ro = parseFloat(this.roundOff) || 0;
                    return Math.max(0, parseFloat((net + ro).toFixed(2)));
                },

                calculateChange() {
                    if (this.paymentMethod !== 'cash') return 0;
                    const received = parseFloat(this.cashReceived) || 0;
                    return Math.max(0, parseFloat((received - this.total).toFixed(2)));
                },

                onPaymentMethodChange() {
                    if (this.paymentMethod === 'cash') {
                        if (this.cashReceived <= 0) {
                            this.cashReceived = this.total;
                        }
                    } else {
                        this.cashReceived = this.total;
                    }
                },

                selectPaymentMethod(method) {
                    this.paymentMethod = method;
                    this.onPaymentMethodChange();
                },

                paymentMethodLabel() {
                    switch (this.paymentMethod) {
                        case 'cash': return 'Cash';
                        case 'upi': return 'UPI';
                        case 'card': return 'Card';
                        case 'transfer': return 'Bank Transfer';
                        default: return this.paymentMethod;
                    }
                },

                async searchItems() {
                    const q = this.itemSearchQuery.trim();
                    if (q.length < 2) {
                        this.searchResults = [];
                        return;
                    }

                    try {
                        const [pRes, sRes] = await Promise.all([
                            fetch('{{ route("ajax.products.search") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ query: q })
                            }).then(r => r.json()).catch(() => []),
                            fetch('{{ route("ajax.services.search") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ query: q })
                            }).then(r => r.json()).catch(() => [])
                        ]);

                        const products = (Array.isArray(pRes) ? pRes : (pRes.data || [])).map(p => ({
                            id: p.id,
                            name: p.name,
                            code: p.product_code || p.sku || '',
                            selling_price: p.selling_price || 0,
                            is_service: false,
                            type: 'product'
                        }));

                        const services = (Array.isArray(sRes) ? sRes : (sRes.data || [])).map(s => ({
                            id: s.id,
                            name: s.name,
                            code: '',
                            selling_price: s.price || s.selling_price || 0,
                            is_service: true,
                            type: 'service'
                        }));

                        this.searchResults = [...products, ...services].slice(0, 10);
                    } catch (e) {
                        console.error('Error fetching items:', e);
                    }
                },

                addItem(result) {
                    const existing = this.items.find(i => 
                        result.is_service ? i.service_id === result.id : i.product_id === result.id
                    );

                    if (existing) {
                        existing.quantity++;
                        this.calculateRow(existing);
                    } else {
                        const price = parseFloat(result.selling_price) || 0;
                        const newItem = {
                            key: Math.random().toString(36).substring(7),
                            product_id: result.is_service ? null : result.id,
                            service_id: result.is_service ? result.id : null,
                            is_service: result.is_service,
                            name: result.name,
                            code: result.code,
                            unit_price: price,
                            discount: 0,
                            quantity: 1,
                            subtotal: price
                        };
                        this.items.push(newItem);
                    }

                    this.itemSearchQuery = '';
                    this.searchResults = [];
                },

                formatMoney(amount) {
                    return '₹' + (parseFloat(amount) || 0).toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
