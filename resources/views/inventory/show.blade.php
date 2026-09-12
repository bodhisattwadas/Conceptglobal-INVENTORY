<x-app-layout title="Inventory Product Details">
    @php
        $product = $inventoryStock->product;
        $batches = $product?->purchaseItems
            ?->filter(fn ($item) => (int) ($item->received_quantity ?? 0) > 0)
            ->sortBy(fn ($item) => [
                $item->purchase?->purchase_date?->timestamp ?? PHP_INT_MAX,
                $item->id,
            ])
            ->values() ?? collect();
        $nextExpiry = $batches->firstWhere('expiry_date', '!=', null)?->expiry_date;
        $movements = $product?->inventoryMovements
            ?->sortByDesc('created_at')
            ->values() ?? collect();
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ $product?->name ?? __('Inventory Product') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">{{ $product?->sku ?: __('Stock and expiry details for this product.') }}</p>
            </div>
            <a href="{{ route('inventory.index') }}" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                {{ __('Back') }}
            </a>
        </div>
    </x-slot>

    <div
        class="py-4"
        x-data="{
            batch: {
                number: '',
                action: '',
                manufacturingDate: '',
                expiryDate: '',
            },
            editBatch(data) {
                this.batch = data;
                this.$dispatch('open-modal', { name: 'batch-date-modal' });
            }
        }"
    >
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold uppercase text-gray-500">{{ __('Product') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ $product?->name ?? '-' }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $product?->company?->short_name ?: ($product?->company?->company_name ?? '-') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500">{{ __('Available Quantity') }}</p>
                        <p class="mt-1 text-3xl font-bold text-emerald-700">{{ $inventoryStock->quantity }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $product?->unit?->symbol ?: $product?->unit?->name ?: __('units') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500">{{ __('Next Expiry Date') }}</p>
                        <p class="mt-1 text-xl font-bold {{ $nextExpiry && $nextExpiry->isPast() ? 'text-red-700' : 'text-gray-900' }}">
                            {{ $nextExpiry?->format('d/m/Y') ?: '-' }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500">{{ __('From received batches') }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    @if($product?->image_url)
                        <div class="overflow-hidden rounded-md border border-gray-200 bg-gray-50">
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="max-h-72 w-full object-contain">
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-2 {{ $product?->image_url ? 'lg:col-span-2' : 'lg:col-span-3' }}">
                        <x-detail-item label="SKU" :value="$product?->sku ?: '-'" />
                        <x-detail-item label="Category" :value="$product?->category?->name ?: '-'" />
                        <x-detail-item label="Unit" :value="$product?->unit ? (($product->unit->name ?? '-') . ($product->unit->symbol ? ' (' . $product->unit->symbol . ')' : '')) : '-'" />
                        <x-detail-item label="Minimum Quantity" :value="$product?->min_stock ?? 0" />
                        <x-detail-item label="MRP" :value="format_money($product?->mrp ?? 0)" />
                        <x-detail-item label="Status" :value="$product?->is_active ? 'Active' : 'Inactive'" />
                        <div class="sm:col-span-2">
                            <x-detail-item label="Description" :value="$product?->description ?: '-'" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Active FIFO Batches') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Only batches with remaining quantity are shown here. Fully sold batches remain in the movement log below.') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Batch No') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('PO Reference') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Supplier / Vendor') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">{{ __('Received Qty') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Net Price') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">{{ __('MFG Date') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">{{ __('Expiry Date') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">{{ __('') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($batches as $item)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-bold text-gray-900">{{ $item->batch_number ?: '-' }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-blue-700">
                                        @if($item->purchase)
                                            <a href="{{ route('purchases.show', $item->purchase) }}" class="hover:underline">
                                                {{ $item->purchase->invoice_number ?: 'PO-'.$item->purchase->id }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $item->purchase?->supplier?->name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-center text-sm font-semibold text-gray-900">{{ $item->received_quantity }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ format_money($item->unit_price) }}</td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-700">{{ $item->manufacturing_date?->format('d/m/Y') ?: '-' }}</td>
                                    <td class="px-4 py-3 text-center text-sm font-semibold {{ $item->expiry_date && $item->expiry_date->isPast() ? 'text-red-700' : 'text-gray-900' }}">
                                        {{ $item->expiry_date?->format('d/m/Y') ?: '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-amber-500 text-white hover:bg-amber-600"
                                            title="{{ __('Update Batch') }}"
                                            x-on:click="editBatch({
                                                number: @js($item->batch_number ?: '-'),
                                                action: @js(route('inventory.batches.update', [$inventoryStock, $item])),
                                                manufacturingDate: @js($item->manufacturing_date?->format('Y-m-d') ?: ''),
                                                expiryDate: @js($item->expiry_date?->format('Y-m-d') ?: '')
                                            })"
                                        >
                                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">{{ __('No received batches found for this product.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Batch Movement Log') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Purchase receipts, FIFO sale deductions, cancellations, and sale restores by batch.') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Type') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Batch No') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Reference') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">{{ __('Qty') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">{{ __('Batch Balance') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($movements as $movement)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $movement->created_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ str($movement->type)->replace('_', ' ')->title() }}</td>
                                    <td class="px-4 py-3 text-sm font-bold text-gray-900">{{ $movement->purchaseItem?->batch_number ?: '-' }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-blue-700">
                                        @if($movement->sale)
                                            <a href="{{ route('sales.show', $movement->sale) }}" class="hover:underline">
                                                {{ $movement->reference ?: $movement->sale->invoice_number }}
                                            </a>
                                        @elseif($movement->purchase)
                                            <a href="{{ route('purchases.show', $movement->purchase) }}" class="hover:underline">
                                                {{ $movement->reference ?: $movement->purchase->invoice_number }}
                                            </a>
                                        @else
                                            {{ $movement->reference ?: '-' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm font-semibold {{ $movement->quantity < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                                        {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm font-semibold text-gray-900">{{ $movement->balance_after }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $movement->notes ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">{{ __('No batch movement logs found for this product.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <x-modal name="batch-date-modal" maxWidth="md" :close-on-outside="false" :close-on-escape="false">
            <form method="POST" x-bind:action="batch.action" class="space-y-5 p-6">
                @csrf
                @method('PATCH')

                <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Update Batch Dates') }}</h3>
                        <p class="mt-2 text-xs font-semibold uppercase text-gray-500">{{ __('Batch No') }}</p>
                        <p class="mt-1 text-lg font-bold text-gray-900" x-text="batch.number"></p>
                    </div>
                    <button
                        type="button"
                        x-on:click="$dispatch('close-modal', { name: 'batch-date-modal' })"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50"
                    >
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>

                <div class="space-y-2">
                    <x-input-label for="modal_manufacturing_date" :value="__('Manufacturing Date')" hint="Manufacturing date printed for this batch. Example: 2026-08-19." />
                    <x-text-input id="modal_manufacturing_date" type="date" name="manufacturing_date" x-model="batch.manufacturingDate" />
                    <x-input-error :messages="$errors->get('manufacturing_date')" class="mt-2" />
                </div>

                <div class="space-y-2">
                    <x-input-label for="modal_expiry_date" :value="__('Expiry Date')" hint="Expiry date printed for this batch. Example: 2027-08-19." />
                    <x-text-input id="modal_expiry_date" type="date" name="expiry_date" x-model="batch.expiryDate" />
                    <x-input-error :messages="$errors->get('expiry_date')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                    <button type="button" x-on:click="$dispatch('close-modal', { name: 'batch-date-modal' })" class="inline-flex h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold shadow-sm"
                        style="display:flex !important; visibility:visible !important; opacity:1 !important; background-color:#059669 !important; color:#ffffff !important; border:1px solid #047857 !important;"
                    >
                        <x-heroicon-o-check class="h-4 w-4" />
                        {{ __('Update Batch') }}
                    </button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
