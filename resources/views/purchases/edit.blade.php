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
            <form action="{{ route('purchases.update', $purchase) }}" method="POST" enctype="multipart/form-data"
                    x-data="purchaseForm({
                        items: {{ Js::from(old('items', $purchase->items->map(function($item) {
                            $mrp = (float) ($item->product->mrp ?? $item->selling_price ?? 0);
                            $unitPrice = (float) $item->unit_price;
                            $discountPercent = $mrp > 0
                                ? round((1 - ($unitPrice / $mrp)) * 100, 2)
                                : 0;
                            return [
                                'product_id' => $item->product_id,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'selling_price' => $item->selling_price,
                                'discount_percent' => $discountPercent,
                                'subtotal' => $item->subtotal,
                                'key' => Str::random(10),
                                'product_name' => $item->product->name ?? '',
                                'product_code' => $item->product->product_code ?? $item->product->sku ?? '',
                                'company_id' => $item->product->company_id ?? null,
                                'brand' => $item->product->company ? ($item->product->company->short_name ?: $item->product->company->company_name) : '',
                                'mrp' => $mrp ?: $item->unit_price ?: 0,
                            ];
                        }))) }},
                        supplier_id: {{ Js::from(old('supplier_id', $purchase->supplier_id)) }},
                        company_id: {{ Js::from(old('company_id', $purchase->company_id)) }},
                        purchase_date: {{ Js::from(old('purchase_date', $purchase->purchase_date->format('Y-m-d'))) }},
                        due_date: {{ Js::from(old('due_date', $purchase->due_date?->format('Y-m-d'))) }},
                        location: {{ Js::from(old('location', $purchase->location?->value ?? $purchase->location ?? 'shop')) }},
                        po_reference: {{ Js::from($purchase->invoice_number) }},
                        is_editing: true,
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
