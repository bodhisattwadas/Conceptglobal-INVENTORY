<x-app-layout title="Edit Purchase Order" header-max-width="max-w-7xl">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Edit Purchase Order') }} #{{ $purchase->id }}
            </h2>
            <x-secondary-button href="{{ route('purchases.index') }}">
                &larr; {{ __('Back to List') }}
            </x-secondary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                    <div class="flex items-center gap-2 font-bold text-red-900">
                        <svg class="h-5 w-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ __('Unable to update Purchase Order. Please resolve the following issues:') }}</span>
                    </div>
                    <ul class="mt-2.5 list-disc list-inside space-y-1 font-medium text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('purchases.update', $purchase) }}" method="POST" enctype="multipart/form-data"
                    x-data="purchaseForm({
                        items: {{ Js::from(old('items', $purchase->items->map(function($item) {
                            $itemSellingPrice = (float) ($item->selling_price ?: 0);
                            $productMrp = (float) ($item->product?->mrp ?: 0);
                            $unitPrice = (float) $item->unit_price;

                            // Prefer the item's recorded MRP/selling price on this PO, then Product Master MRP, then unit price
                            $mrp = $itemSellingPrice > 0 ? $itemSellingPrice : ($productMrp > 0 ? $productMrp : $unitPrice);

                            // Ensure MRP is at least unit price so discount percent cannot be negative
                            if ($unitPrice > $mrp) {
                                $mrp = $unitPrice;
                            }

                            // Calculate discount percent safely clamped to 0% - 100%
                            if ($item->discount_percent !== null && is_numeric($item->discount_percent)) {
                                $discountPercent = (float) $item->discount_percent;
                            } elseif ($mrp > 0 && $mrp >= $unitPrice) {
                                $discountPercent = round((1 - ($unitPrice / $mrp)) * 100, 2);
                            } else {
                                $discountPercent = 0.0;
                            }
                            $discountPercent = max(0, min(100, $discountPercent));

                            $calculatedUnitPrice = $mrp > 0 ? round($mrp * (1 - ($discountPercent / 100)), 2) : $unitPrice;
                            if (abs($unitPrice - $calculatedUnitPrice) < 0.05) {
                                $calculatedUnitPrice = $unitPrice;
                            }
                            $qty = (int) $item->quantity;
                            $calculatedSubtotal = round($qty * $calculatedUnitPrice, 2);

                            return [
                                'product_id' => $item->product_id,
                                'quantity' => $qty,
                                'unit_price' => $calculatedUnitPrice,
                                'selling_price' => $mrp,
                                'discount_percent' => $discountPercent,
                                'subtotal' => $calculatedSubtotal,
                                'key' => Str::random(10),
                                'product_name' => $item->product->name ?? '',
                                'product_code' => $item->product->product_code ?? $item->product->sku ?? '',
                                'company_id' => $item->product->company_id ?? null,
                                'brand' => $item->product->company ? ($item->product->company->short_name ?: $item->product->company->company_name) : '',
                                'mrp' => $mrp,
                                'batch_number' => $item->batch_number ?? '',
                                'received_quantity' => (int) ($item->received_quantity ?? $item->quantity),
                                'min_quantity' => max(1, (int) $item->quantity - (int) ($item->received_quantity ?? $item->quantity)),
                            ];
                        }))) }},
                        supplier_id: {{ Js::from(old('supplier_id', $purchase->supplier_id)) }},
                        company_id: {{ Js::from(old('company_id', $purchase->company_id)) }},
                        purchase_date: {{ Js::from(old('purchase_date', $purchase->purchase_date->format('Y-m-d'))) }},
                        due_date: {{ Js::from(old('due_date', $purchase->due_date?->format('Y-m-d'))) }},
                        location: {{ Js::from(old('location', $purchase->location?->value ?? $purchase->location ?? 'shop')) }},
                        po_reference: {{ Js::from($purchase->invoice_number) }},
                        is_editing: true,
                        is_received: {{ Js::from($purchase->status === \App\Enums\PurchaseStatus::RECEIVED) }},
                        status: {{ Js::from(old('status', $purchase->status->value)) }},
                        errors: {{ Js::from($errors->any() ? $errors->toArray() : []) }}
                    })"
                    @submit.prevent="submitForm">
                @csrf
                @method('PUT')

                @include('purchases.form')

            </form>
        </div>
    </div>
</x-app-layout>
