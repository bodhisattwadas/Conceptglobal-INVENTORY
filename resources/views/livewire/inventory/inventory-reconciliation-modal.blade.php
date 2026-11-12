<div>
    @if($isOpen && $reconciliation)
        <div
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <!-- Background Backdrop -->
            <div
                class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"
                wire:click="closeModal"
            ></div>

            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-gray-200">

                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 px-6 py-4 text-white flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-amber-400">{{ $reconciliation->reconciliation_number }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $reconciliation->isPending() ? 'bg-amber-400/20 text-amber-300 border border-amber-400/30' : 'bg-emerald-400/20 text-emerald-300 border border-emerald-400/30' }}">
                                    {{ $reconciliation->status }}
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-white mt-1">
                                {{ __('Reconcile: ') }} {{ $reconciliation->item_name }}
                            </h3>
                            <div class="text-xs text-gray-300 flex items-center gap-3 mt-0.5">
                                <span>{{ __('Sold Qty: ') }} <strong>{{ $reconciliation->quantity }}</strong></span>
                                <span>•</span>
                                <span>{{ __('Unit Price: ') }} <strong>{{ format_money($reconciliation->unit_price) }}</strong></span>
                                @if($reconciliation->barcode)
                                    <span>•</span>
                                    <span>{{ __('Barcode: ') }} <strong class="font-mono">{{ $reconciliation->barcode }}</strong></span>
                                @endif
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="rounded-lg p-1.5 text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Tabs Switcher -->
                    <div class="border-b border-gray-200 bg-gray-50/80 px-6 pt-3">
                        <nav class="flex space-x-2 -mb-px flex-wrap gap-y-1">
                            <button
                                type="button"
                                wire:click="$set('activeTab', 'po')"
                                class="inline-flex items-center gap-1.5 py-2.5 px-3 text-xs font-bold border-b-2 transition-all {{ $activeTab === 'po' ? 'border-indigo-600 text-indigo-700 bg-white rounded-t-lg shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>{{ __('1. Link to PO') }}</span>
                            </button>

                            <button
                                type="button"
                                wire:click="$set('activeTab', 'link')"
                                class="inline-flex items-center gap-1.5 py-2.5 px-3 text-xs font-bold border-b-2 transition-all {{ $activeTab === 'link' ? 'border-blue-600 text-blue-700 bg-white rounded-t-lg shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                <span>{{ __('2. Link Product') }}</span>
                            </button>

                            <button
                                type="button"
                                wire:click="$set('activeTab', 'create')"
                                class="inline-flex items-center gap-1.5 py-2.5 px-3 text-xs font-bold border-b-2 transition-all {{ $activeTab === 'create' ? 'border-emerald-600 text-emerald-700 bg-white rounded-t-lg shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                <span>{{ __('3. Create in Master') }}</span>
                            </button>

                            <button
                                type="button"
                                wire:click="$set('activeTab', 'direct')"
                                class="inline-flex items-center gap-1.5 py-2.5 px-3 text-xs font-bold border-b-2 transition-all {{ $activeTab === 'direct' ? 'border-amber-600 text-amber-700 bg-white rounded-t-lg shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{{ __('4. Mark Reconciled') }}</span>
                            </button>

                            <button
                                type="button"
                                wire:click="$set('activeTab', 'edit')"
                                class="inline-flex items-center gap-1.5 py-2.5 px-3 text-xs font-bold border-b-2 transition-all {{ $activeTab === 'edit' ? 'border-purple-600 text-purple-700 bg-white rounded-t-lg shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                <span>{{ __('Edit All Fields') }}</span>
                            </button>
                        </nav>
                    </div>

                    <!-- Modal Body Container -->
                    <div class="p-6 max-h-[65vh] overflow-y-auto">

                        <!-- ================= TAB: LINK TO PURCHASE ORDER ================= -->
                        @if($activeTab === 'po')
                            <div class="space-y-4">
                                <div class="bg-indigo-50/70 rounded-xl p-3.5 border border-indigo-200 text-xs text-indigo-900 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        {{ __('Link to Purchase Order (Regularize Inward Shipment)') }}
                                    </div>
                                    <p class="text-indigo-700">
                                        {{ __('If goods were delivered by a vendor and sold at POS prior to PO entry, select the corresponding Purchase Order below. If the PO is already Received, the sold quantity will be deducted from the new batch immediately. If Ordered/Draft, it will auto-settle upon receiving.') }}
                                    </p>
                                </div>

                                <!-- Selected Purchase Card (if chosen) -->
                                @if($selectedPurchase)
                                    <div class="rounded-xl border-2 border-indigo-500 bg-indigo-50/30 p-4 relative">
                                        <button
                                            type="button"
                                            wire:click="clearSelectedPurchase"
                                            class="absolute top-2 right-2 p-1 text-gray-400 hover:text-red-600"
                                            title="{{ __('Choose a different PO') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-indigo-700 uppercase tracking-wider">{{ __('Selected Purchase Order') }}</span>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $selectedPurchase->status->color() }}">
                                                {{ $selectedPurchase->status->label() }}
                                            </span>
                                        </div>
                                        <div class="text-sm font-bold text-gray-900 mt-1 font-mono">
                                            {{ $selectedPurchase->invoice_number }}
                                            @if($selectedPurchase->supplier)
                                                <span class="text-gray-600 font-normal">({{ $selectedPurchase->supplier->name }})</span>
                                            @endif
                                        </div>
                                        <div class="mt-2 text-xs text-gray-600 grid grid-cols-2 sm:grid-cols-3 gap-2">
                                            <div>Date: <strong>{{ $selectedPurchase->purchase_date?->format('d M Y') ?? 'N/A' }}</strong></div>
                                            <div>Items in PO: <strong>{{ $selectedPurchase->items->count() }}</strong></div>
                                            <div>Total PO Value: <strong>{{ format_money($selectedPurchase->total) }}</strong></div>
                                        </div>

                                        @if($selectedPurchase->items->isNotEmpty())
                                            <div class="mt-3 pt-3 border-t border-indigo-100">
                                                <label class="block text-xs font-semibold text-gray-700 mb-1">
                                                    {{ __('Select Matching Line Item in this PO:') }}
                                                </label>
                                                <select
                                                    wire:model="selectedPurchaseItemId"
                                                    class="w-full text-xs rounded-lg border-gray-300 py-1.5 px-3 focus:ring-indigo-500 focus:border-indigo-500 font-medium"
                                                >
                                                    @foreach($selectedPurchase->items as $pItem)
                                                        <option value="{{ $pItem->id }}">
                                                            {{ $pItem->product?->name ?? 'Product #'.$pItem->product_id }} - Qty: {{ $pItem->quantity }} @ {{ format_money($pItem->unit_price) }} (Buy)
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <!-- Search Input -->
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                                {{ __('Search Purchase Orders') }}
                                            </label>
                                            @if($reconciliation->matched_product_id)
                                                <button
                                                    type="button"
                                                    wire:click="createPurchaseOrderNow"
                                                    class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold inline-flex items-center gap-1"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                                    <span>{{ __('+ Create New PO for this item') }}</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div class="relative">
                                            <input
                                                type="text"
                                                wire:model.live.debounce.300ms="poSearch"
                                                placeholder="{{ __('Search by PO #, Supplier Name, or Product Name...') }}"
                                                class="w-full text-xs rounded-lg border-gray-300 py-2 pl-9 pr-4 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
                                            >
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                            </div>
                                        </div>

                                        <!-- PO Search Results List -->
                                        <div class="border border-gray-200 rounded-xl divide-y divide-gray-100 max-h-56 overflow-y-auto">
                                            @forelse($this->poSearchResults as $po)
                                                <div
                                                    wire:click="selectPurchase({{ $po->id }})"
                                                    class="p-3 hover:bg-indigo-50/60 cursor-pointer flex items-center justify-between transition-colors text-xs"
                                                >
                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-mono font-bold text-gray-900">{{ $po->invoice_number }}</span>
                                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold {{ $po->status->color() }}">
                                                                {{ $po->status->label() }}
                                                            </span>
                                                        </div>
                                                        <div class="text-gray-500 mt-0.5">
                                                            {{ $po->supplier?->name ?? 'No Supplier' }} • {{ $po->purchase_date?->format('d M Y') }}
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="font-bold text-gray-900">{{ format_money($po->total) }}</div>
                                                        <div class="text-[10px] text-indigo-600 font-semibold">{{ __('Select PO &rarr;') }}</div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="p-6 text-center text-xs text-gray-400">
                                                    {{ __('No purchase orders found matching query.') }}
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- ================= TAB 1: LINK EXISTING PRODUCT ================= -->
                        @if($activeTab === 'link')
                            <div class="space-y-4">
                                <div class="bg-blue-50/60 rounded-xl p-3.5 border border-blue-200 text-xs text-blue-800 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        {{ __('Link to an Existing Product Master') }}
                                    </div>
                                    <p class="text-blue-700">
                                        {{ __('Select the matching product master item. The sold quantity will be deducted from shop stock and the sale record will be updated.') }}
                                    </p>
                                </div>

                                <!-- Selected Product Card (if chosen) -->
                                @if($selectedProduct)
                                    <div class="rounded-xl border-2 border-blue-500 bg-blue-50/30 p-4 relative">
                                        <button
                                            type="button"
                                            wire:click="clearSelectedProduct"
                                            class="absolute top-2 right-2 p-1 text-gray-400 hover:text-red-600"
                                            title="{{ __('Change product') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                        <div class="text-xs font-semibold text-blue-600 uppercase tracking-wider">{{ __('Selected Master Product') }}</div>
                                        <div class="text-sm font-bold text-gray-900 mt-1">{{ $selectedProduct->name }}</div>
                                        <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                            <div class="bg-white p-2 rounded border border-blue-200">
                                                <span class="text-gray-400 block text-[10px]">{{ __('SKU') }}</span>
                                                <span class="font-mono font-bold">{{ $selectedProduct->sku }}</span>
                                            </div>
                                            <div class="bg-white p-2 rounded border border-blue-200">
                                                <span class="text-gray-400 block text-[10px]">{{ __('Barcode') }}</span>
                                                <span class="font-mono font-bold">{{ $selectedProduct->product_code ?: '-' }}</span>
                                            </div>
                                            <div class="bg-white p-2 rounded border border-blue-200">
                                                <span class="text-gray-400 block text-[10px]">{{ __('Selling Price') }}</span>
                                                <span class="font-bold text-emerald-700">{{ format_money($selectedProduct->selling_price) }}</span>
                                            </div>
                                            <div class="bg-white p-2 rounded border border-blue-200">
                                                <span class="text-gray-400 block text-[10px]">{{ __('Current Stock') }}</span>
                                                <span class="font-bold text-indigo-700">{{ $selectedProduct->quantity }} {{ $selectedProduct->unit?->symbol }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <!-- Search input -->
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Search Product Master') }}</label>
                                        <div class="relative">
                                            <input
                                                type="text"
                                                wire:model.live.debounce.300ms="productSearch"
                                                placeholder="{{ __('Type name, barcode, or SKU...') }}"
                                                class="w-full text-xs rounded-lg border-gray-300 py-2.5 px-3 focus:ring-blue-500 focus:border-blue-500"
                                            >
                                        </div>
                                        <p class="text-[11px] text-gray-500 mt-1">{{ __('Type at least 2 characters to search product catalog.') }}</p>
                                    </div>

                                    <!-- Search Results -->
                                    @if($searchResults->isNotEmpty())
                                        <div class="max-h-56 overflow-y-auto divide-y divide-gray-100 border border-gray-200 rounded-lg">
                                            @foreach($searchResults as $prod)
                                                <div
                                                    wire:click="selectProduct({{ $prod->id }})"
                                                    class="p-2.5 hover:bg-blue-50 cursor-pointer flex items-center justify-between transition-colors"
                                                >
                                                    <div class="min-w-0 pr-3">
                                                        <div class="text-xs font-bold text-gray-900 truncate">{{ $prod->name }}</div>
                                                        <div class="text-[11px] text-gray-500 font-mono mt-0.5 flex items-center gap-2">
                                                            <span>SKU: {{ $prod->sku }}</span>
                                                            @if($prod->product_code)
                                                                <span>• Barcode: {{ $prod->product_code }}</span>
                                                            @endif
                                                            @if($prod->company)
                                                                <span>• {{ $prod->company->short_name ?: $prod->company->company_name }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="text-right shrink-0">
                                                        <div class="text-xs font-bold text-blue-700">{{ format_money($prod->selling_price) }}</div>
                                                        <div class="text-[10px] text-gray-400">Stock: {{ $prod->quantity }} {{ $prod->unit?->symbol }}</div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif

                                <!-- Deduct Stock Checkbox -->
                                <div class="flex items-start gap-2 pt-2 border-t border-gray-100">
                                    <input
                                        type="checkbox"
                                        id="deductStock"
                                        wire:model="deductStock"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mt-0.5"
                                    >
                                    <label for="deductStock" class="text-xs text-gray-700 cursor-pointer">
                                        <span class="font-bold">{{ __('Deduct sold quantity (:qty) from inventory stock', ['qty' => $reconciliation->quantity]) }}</span>
                                        <span class="block text-gray-500 text-[11px]">{{ __('Creates a stock movement and deducts physical inventory via FIFO.') }}</span>
                                    </label>
                                </div>

                                <!-- Reconciliation Notes -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Admin Notes (Optional)') }}</label>
                                    <textarea
                                        wire:model="linkNotes"
                                        rows="2"
                                        placeholder="{{ __('Add notes about this reconciliation action...') }}"
                                        class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
                                    ></textarea>
                                </div>
                            </div>
                        @endif

                        <!-- ================= TAB 2: CREATE IN MASTER ================= -->
                        @if($activeTab === 'create')
                            <div class="space-y-3.5">
                                <div class="bg-emerald-50/60 rounded-xl p-3.5 border border-emerald-200 text-xs text-emerald-800 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        {{ __('Create Brand New Product in Master Catalog') }}
                                    </div>
                                    <p class="text-emerald-700">
                                        {{ __('The item does not exist in master. Enter details to register it. It will be added to products and linked to this sale.') }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Product Name *') }}</label>
                                        <input
                                            type="text"
                                            wire:model="create_name"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-emerald-500 focus:border-emerald-500"
                                            required
                                        >
                                        @error('create_name') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Barcode / Product Code') }}</label>
                                        <input
                                            type="text"
                                            wire:model="create_barcode"
                                            placeholder="{{ __('e.g. 890123456789') }}"
                                            class="w-full text-xs font-mono rounded-lg border-gray-300 py-2 px-3 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('SKU') }}</label>
                                        <input
                                            type="text"
                                            wire:model="create_sku"
                                            class="w-full text-xs font-mono rounded-lg border-gray-300 py-2 px-3 focus:ring-emerald-500 focus:border-emerald-500 bg-gray-50"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Brand / Company') }}</label>
                                        <select
                                            wire:model="create_company_id"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            <option value="">{{ __('Select Brand...') }}</option>
                                            @foreach($companies as $company)
                                                <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Category') }}</label>
                                        <select
                                            wire:model="create_category_id"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            <option value="">{{ __('Select Category...') }}</option>
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Unit') }}</label>
                                        <select
                                            wire:model="create_unit_id"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            <option value="">{{ __('Select Unit...') }}</option>
                                            @foreach($units as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->symbol }})</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Selling Price / MRP *') }}</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model="create_selling_price"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-emerald-500 focus:border-emerald-500"
                                            required
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Purchase Cost') }}</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model="create_purchase_price"
                                            placeholder="0.00"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Initial Physical Stock Received') }}</label>
                                        <input
                                            type="number"
                                            wire:model="create_initial_stock"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                    </div>
                                </div>

                                <div class="flex items-start gap-2 pt-2 border-t border-gray-100">
                                    <input
                                        type="checkbox"
                                        id="create_deduct_sold"
                                        wire:model="create_deduct_sold"
                                        class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 mt-0.5"
                                    >
                                    <label for="create_deduct_sold" class="text-xs text-gray-700 cursor-pointer">
                                        <span class="font-bold">{{ __('Deduct sold quantity (:qty) from initial stock', ['qty' => $reconciliation->quantity]) }}</span>
                                        <span class="block text-gray-500 text-[11px]">{{ __('Net remaining stock in shop will be :net.', ['net' => max(0, $create_initial_stock - $reconciliation->quantity)]) }}</span>
                                    </label>
                                </div>
                            </div>
                        @endif

                        <!-- ================= TAB 3: DIRECT MARK RECONCILED ================= -->
                        @if($activeTab === 'direct')
                            <div class="space-y-4">
                                <div class="bg-amber-50/60 rounded-xl p-3.5 border border-amber-200 text-xs text-amber-800 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        {{ __('Direct Reconcile (Administrative Verification)') }}
                                    </div>
                                    <p class="text-amber-700">
                                        {{ __('Use this option if the physical inventory was already verified, written off, or accounted for during periodic stock audits without altering catalog products.') }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Resolution Notes *') }}</label>
                                    <textarea
                                        wire:model="directNotes"
                                        rows="4"
                                        placeholder="{{ __('Enter explanation (e.g. Physical inventory counted and reconciled against old supplier batch #12)...') }}"
                                        class="w-full text-xs rounded-lg border-gray-300 py-2.5 px-3 focus:ring-amber-500 focus:border-amber-500"
                                        required
                                    ></textarea>
                                </div>
                            </div>
                        @endif

                        <!-- ================= TAB 4: EDIT ALL FIELDS ================= -->
                        @if($activeTab === 'edit')
                            <div class="space-y-3.5">
                                <div class="bg-purple-50/60 rounded-xl p-3.5 border border-purple-200 text-xs text-purple-800 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        {{ __('Edit All Details of this Reconciliation Entry') }}
                                    </div>
                                    <p class="text-purple-700">
                                        {{ __('All fields can be modified. You can also synchronize the edited name and prices back to the sale receipt.') }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Item Name') }}</label>
                                        <input
                                            type="text"
                                            wire:model="edit_item_name"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-purple-500 focus:border-purple-500"
                                            required
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Barcode') }}</label>
                                        <input
                                            type="text"
                                            wire:model="edit_barcode"
                                            class="w-full text-xs font-mono rounded-lg border-gray-300 py-2 px-3 focus:ring-purple-500 focus:border-purple-500"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Quantity Sold') }}</label>
                                        <input
                                            type="number"
                                            wire:model="edit_quantity"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-purple-500 focus:border-purple-500"
                                            required
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Unit Price (MRP)') }}</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model="edit_unit_price"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-purple-500 focus:border-purple-500"
                                            required
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Discount') }}</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model="edit_discount"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-purple-500 focus:border-purple-500"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Cost Price') }}</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model="edit_cost_price"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-purple-500 focus:border-purple-500"
                                        >
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Status') }}</label>
                                        <select
                                            wire:model="edit_status"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-purple-500 focus:border-purple-500"
                                        >
                                            <option value="pending">{{ __('Pending') }}</option>
                                            <option value="reconciled">{{ __('Reconciled') }}</option>
                                            <option value="cancelled">{{ __('Cancelled / Rejected') }}</option>
                                        </select>
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">{{ __('Notes') }}</label>
                                        <textarea
                                            wire:model="edit_notes"
                                            rows="2"
                                            class="w-full text-xs rounded-lg border-gray-300 py-2 px-3 focus:ring-purple-500 focus:border-purple-500"
                                        ></textarea>
                                    </div>
                                </div>

                                <div class="flex items-start gap-2 pt-2 border-t border-gray-100">
                                    <input
                                        type="checkbox"
                                        id="edit_sync_sale"
                                        wire:model="edit_sync_sale"
                                        class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 mt-0.5"
                                    >
                                    <label for="edit_sync_sale" class="text-xs text-gray-700 cursor-pointer">
                                        <span class="font-bold">{{ __('Synchronize changes to the original Sale invoice') }}</span>
                                        <span class="block text-gray-500 text-[11px]">{{ __('Updates the sale line item and recalculates invoice totals.') }}</span>
                                    </label>
                                </div>
                            </div>
                        @endif

                    </div>

                    <!-- Modal Actions Footer -->
                    <div class="bg-gray-50 px-6 py-3.5 flex items-center justify-between border-t border-gray-200">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            {{ __('Cancel') }}
                        </button>

                        <div>
                            @if($activeTab === 'po')
                                <button
                                    type="button"
                                    wire:click="submitLinkPurchase"
                                    wire:loading.attr="disabled"
                                    :disabled="!$wire.selectedPurchaseId"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    <span>{{ __('Link to Purchase Order') }}</span>
                                </button>
                            @elseif($activeTab === 'link')
                                <button
                                    type="button"
                                    wire:click="submitLink"
                                    wire:loading.attr="disabled"
                                    :disabled="!$wire.selectedProductId"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    <span>{{ __('Link Product & Mark Reconciled') }}</span>
                                </button>
                            @elseif($activeTab === 'create')
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="submitCreateProductAndDraftPO"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 transition-colors"
                                        title="{{ __('Save product in Master Catalog and immediately open PO form') }}"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                        <span>{{ __('Create Master & Draft PO') }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="submitCreate"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:opacity-50 transition-colors"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                        <span>{{ __('Create & Settle Directly') }}</span>
                                    </button>
                                </div>
                            @elseif($activeTab === 'direct')
                                <button
                                    type="button"
                                    wire:click="submitDirect"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-amber-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 disabled:opacity-50 transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    <span>{{ __('Mark Reconciled Directly') }}</span>
                                </button>
                            @elseif($activeTab === 'edit')
                                <button
                                    type="button"
                                    wire:click="submitEdit"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-purple-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:opacity-50 transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    <span>{{ __('Save Changes') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
