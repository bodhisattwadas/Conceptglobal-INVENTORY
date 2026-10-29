<div>
    @if($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-gray-500/75 transition-opacity" wire:click="closeModal"></div>

                <!-- Modal Panel -->
                <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                    <form wire:submit.prevent="submitAdjustment">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900" id="modal-title">
                                    {{ __('Inventory Stock Adjustment') }}
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ __('Reallocate stock to another product or adjust inventory count.') }}
                                </p>
                            </div>
                            <button type="button" wire:click="closeModal" class="rounded-md text-gray-400 hover:text-gray-500 focus:outline-none">
                                <span class="sr-only">Close</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
                            <!-- Adjustment Type Selector -->
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">{{ __('Adjustment Mode') }}</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        type="button"
                                        wire:click="$set('adjustmentType', 'reallocation')"
                                        class="flex flex-col items-center justify-center p-2.5 rounded-lg border text-center transition-all {{ $adjustmentType === 'reallocation' ? 'border-blue-600 bg-blue-50/70 text-blue-900 ring-2 ring-blue-500/30 font-semibold' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }}"
                                    >
                                        <svg class="h-5 w-5 mb-1 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                        <span class="text-xs">{{ __('Reallocate Product') }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="$set('adjustmentType', 'quantity_decrease')"
                                        class="flex flex-col items-center justify-center p-2.5 rounded-lg border text-center transition-all {{ $adjustmentType === 'quantity_decrease' ? 'border-red-600 bg-red-50/70 text-red-900 ring-2 ring-red-500/30 font-semibold' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }}"
                                    >
                                        <svg class="h-5 w-5 mb-1 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-xs">{{ __('Write-off / Damage') }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="$set('adjustmentType', 'quantity_increase')"
                                        class="flex flex-col items-center justify-center p-2.5 rounded-lg border text-center transition-all {{ $adjustmentType === 'quantity_increase' ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900 ring-2 ring-emerald-500/30 font-semibold' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }}"
                                    >
                                        <svg class="h-5 w-5 mb-1 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-xs">{{ __('Found / Count Surplus') }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Source Product -->
                            <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-4 space-y-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center gap-1.5">
                                    <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                                    {{ $adjustmentType === 'reallocation' ? __('Source Product (Deduct From)') : __('Product') }}
                                </h4>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Select Product') }} <span class="text-red-500">*</span></label>
                                    <select
                                        wire:model.live="source_product_id"
                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                    >
                                        <option value="">{{ __('-- Select Product --') }}</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">
                                                {{ $p->name }} ({{ $p->product_code ?: $p->sku }}) - Stock: {{ $p->quantity }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('source_product_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>

                                @if($source_product_id)
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Select Active Batch') }} <span class="text-red-500">*</span></label>
                                        <select
                                            wire:model.live="source_purchase_item_id"
                                            class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                        >
                                            <option value="">{{ __('-- Select Batch --') }}</option>
                                            @foreach($sourceBatches as $b)
                                                <option value="{{ $b->id }}">
                                                    Batch: {{ $b->batch_number ?: 'N/A' }} | Available: {{ $b->received_quantity }} units @ ₹{{ number_format($b->unit_price, 2) }}
                                                    @if($b->purchase) (PO: {{ $b->purchase->invoice_number }}) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('source_purchase_item_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

                                        @if($selectedBatch)
                                            <div class="mt-2 rounded-md bg-blue-50/80 p-2.5 border border-blue-200 text-xs text-blue-950 flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <span class="font-semibold">{{ __('Batch Available:') }}</span>
                                                    <span class="font-bold text-emerald-700 font-mono text-sm">{{ $selectedBatch->received_quantity }}</span> units
                                                    <span class="mx-1 text-gray-400">|</span>
                                                    <span class="font-semibold">{{ __('Cost Price:') }}</span>
                                                    <span class="font-mono">₹{{ number_format($selectedBatch->unit_price, 2) }}</span>
                                                </div>
                                                @if($selectedBatch->purchase?->supplier)
                                                    <div class="text-gray-600">
                                                        Vendor: <span class="font-medium text-gray-800">{{ $selectedBatch->purchase->supplier->name }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Target Product (Only in Reallocation Mode) -->
                            @if($adjustmentType === 'reallocation')
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50/30 p-4 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-900 flex items-center gap-1.5">
                                            <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                                            {{ __('Destination Product (Add Stock To)') }}
                                        </h4>
                                        <button
                                            type="button"
                                            wire:click="toggleQuickProduct"
                                            class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline"
                                        >
                                            @if($showQuickProduct)
                                                <span>{{ __('← Select Existing Product') }}</span>
                                            @else
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                <span>{{ __('+ Create New Product') }}</span>
                                            @endif
                                        </button>
                                    </div>

                                    @if($showQuickProduct)
                                        <!-- Inline Quick Create Product Form -->
                                        <div class="rounded-md border border-blue-200 bg-white p-3.5 space-y-3 shadow-xs">
                                            <div class="border-b border-gray-100 pb-2">
                                                <p class="text-xs font-bold text-gray-900">{{ __('Quick Product Master Entry') }}</p>
                                                <p class="text-[11px] text-gray-500">{{ __('Create the missing product master row right now.') }}</p>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div class="sm:col-span-2">
                                                    <label class="block text-xs font-medium text-gray-700 mb-0.5">{{ __('Product Name') }} <span class="text-red-500">*</span></label>
                                                    <input
                                                        type="text"
                                                        wire:model="quick_name"
                                                        placeholder="e.g. Skin Glow Cream Shade B"
                                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    >
                                                    @error('quick_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-0.5">{{ __('Product Code / Barcode') }}</label>
                                                    <input
                                                        type="text"
                                                        wire:model="quick_product_code"
                                                        placeholder="Auto-generated if empty"
                                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm font-mono focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    >
                                                    @error('quick_product_code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-0.5">{{ __('MRP / Selling Price (₹)') }}</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        wire:model="quick_mrp"
                                                        placeholder="0.00"
                                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm text-right focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    >
                                                    @error('quick_mrp') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-0.5">{{ __('Brand / Company') }}</label>
                                                    <select
                                                        wire:model="quick_company_id"
                                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    >
                                                        <option value="">{{ __('-- Optional Brand --') }}</option>
                                                        @foreach($companies as $c)
                                                            <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-0.5">{{ __('Category') }}</label>
                                                    <select
                                                        wire:model="quick_category_id"
                                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    >
                                                        <option value="">{{ __('-- Optional Category --') }}</option>
                                                        @foreach($categories as $cat)
                                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-0.5">{{ __('Unit') }}</label>
                                                    <select
                                                        wire:model="quick_unit_id"
                                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    >
                                                        <option value="">{{ __('-- Optional Unit --') }}</option>
                                                        @foreach($units as $u)
                                                            <option value="{{ $u->id }}">{{ $u->name }}{{ $u->symbol ? ' (' . $u->symbol . ')' : '' }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="flex justify-end pt-2">
                                                <button
                                                    type="button"
                                                    wire:click="saveQuickProduct"
                                                    wire:loading.attr="disabled"
                                                    class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors"
                                                >
                                                    <span wire:loading.remove wire:target="saveQuickProduct">{{ __('Save & Select Product') }}</span>
                                                    <span wire:loading wire:target="saveQuickProduct">{{ __('Creating...') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    @else
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Select Destination Product') }} <span class="text-red-500">*</span></label>
                                            <select
                                                wire:model.live="target_product_id"
                                                class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                            >
                                                <option value="">{{ __('-- Select Target Product --') }}</option>
                                                @foreach($products as $p)
                                                    @if($p->id !== $source_product_id)
                                                        <option value="{{ $p->id }}">
                                                            {{ $p->name }} ({{ $p->product_code ?: $p->sku }}) - Current Stock: {{ $p->quantity }}
                                                        </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            @error('target_product_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                        </div>

                                        @if($target_product_id)
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Destination Product MRP (₹)') }}</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    wire:model="target_mrp"
                                                    class="block w-full sm:w-1/2 rounded-md border-gray-300 py-1.5 px-3 text-sm text-right focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                                    placeholder="0.00"
                                                >
                                                <p class="text-[11px] text-gray-500 mt-0.5">{{ __('Unit cost price will be inherited directly from the source batch.') }}</p>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endif

                            <!-- Quantity & Reason -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-xs font-medium text-gray-700">{{ __('Quantity to Adjust') }} <span class="text-red-500">*</span></label>
                                        @if($selectedBatch)
                                            <button
                                                type="button"
                                                wire:click="$set('quantity', {{ $selectedBatch->received_quantity }})"
                                                class="text-[11px] font-semibold text-blue-600 hover:text-blue-800"
                                            >
                                                {{ __('Max: ') }}{{ $selectedBatch->received_quantity }}
                                            </button>
                                        @endif
                                    </div>
                                    <input
                                        type="number"
                                        min="1"
                                        @if($selectedBatch) max="{{ $selectedBatch->received_quantity }}" @endif
                                        wire:model="quantity"
                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm text-center font-bold focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                    >
                                    @error('quantity') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Reason') }} <span class="text-red-500">*</span></label>
                                    <select
                                        wire:model="reason"
                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                    >
                                        @if($adjustmentType === 'reallocation')
                                            <option value="misallocation_split">{{ __('PO Misallocation / Product Split') }}</option>
                                            <option value="count_correction">{{ __('Physical Count Discrepancy') }}</option>
                                            <option value="other">{{ __('Other Reallocation') }}</option>
                                        @elseif($adjustmentType === 'quantity_decrease')
                                            <option value="damaged">{{ __('Damaged Goods') }}</option>
                                            <option value="expired">{{ __('Expired Stock') }}</option>
                                            <option value="theft_loss">{{ __('Theft / Shrinkage') }}</option>
                                            <option value="count_correction">{{ __('Physical Count Shortage') }}</option>
                                        @else
                                            <option value="found_correction">{{ __('Found Goods / Count Surplus') }}</option>
                                            <option value="supplier_bonus">{{ __('Supplier Free Samples / Bonus') }}</option>
                                        @endif
                                    </select>
                                    @error('reason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Notes & Audit Remarks') }}</label>
                                    <textarea
                                        wire:model="notes"
                                        rows="2"
                                        placeholder="Optional explanation (e.g. Received 3 units of Shade B on PO-261028 under Shade A line item)"
                                        class="block w-full rounded-md border-gray-300 py-1.5 px-3 text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                                    ></textarea>
                                    @error('notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-gray-200 bg-gray-50 px-6 py-3 flex items-center justify-end gap-3">
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center justify-center rounded-md bg-blue-600 py-2 px-4 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors"
                            >
                                <span wire:loading.remove wire:target="submitAdjustment">
                                    {{ $adjustmentType === 'reallocation' ? __('Confirm Reallocation') : __('Confirm Adjustment') }}
                                </span>
                                <span wire:loading wire:target="submitAdjustment">
                                    {{ __('Processing...') }}
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
