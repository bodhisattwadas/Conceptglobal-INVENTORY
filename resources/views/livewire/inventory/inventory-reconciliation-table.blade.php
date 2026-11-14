<div
    x-data="{
        tooltipText: '',
        tooltipVisible: false,
        tooltipX: 0,
        tooltipY: 0,
        tooltipPlacement: 'bottom',
        showTooltip(e, text) {
            if (!text) return;
            this.tooltipText = text;
            this.tooltipVisible = true;
            const rect = e.currentTarget.getBoundingClientRect();
            let left = rect.left;
            const maxW = Math.min(380, window.innerWidth - 32);
            if (left + maxW > window.innerWidth - 16) {
                left = window.innerWidth - maxW - 16;
            }
            if (left < 16) left = 16;

            if (rect.bottom + 140 > window.innerHeight && rect.top > 140) {
                this.tooltipY = rect.top - 6;
                this.tooltipPlacement = 'top';
            } else {
                this.tooltipY = rect.bottom + 6;
                this.tooltipPlacement = 'bottom';
            }
            this.tooltipX = left;
        },
        hideTooltip() {
            this.tooltipVisible = false;
        }
    }"
    class="space-y-4"
>
    <!-- Top Filter Header: Tabs & Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <!-- Status Tabs -->
        <div class="inline-flex rounded-lg p-1 bg-gray-100 border border-gray-200 gap-1 flex-wrap">
            <button
                type="button"
                wire:click="setStatusFilter('pending')"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-bold transition-all {{ $statusFilter === 'pending' ? 'bg-white text-amber-800 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
            >
                <span>{{ __('Pending Reconciliation') }}</span>
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $statusFilter === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-gray-200 text-gray-700' }}">
                    {{ $counts['pending'] }}
                </span>
            </button>

            <button
                type="button"
                wire:click="setStatusFilter('reconciled')"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-bold transition-all {{ $statusFilter === 'reconciled' ? 'bg-white text-emerald-800 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
            >
                <span>{{ __('Reconciled') }}</span>
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $statusFilter === 'reconciled' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700' }}">
                    {{ $counts['reconciled'] }}
                </span>
            </button>

            <button
                type="button"
                wire:click="setStatusFilter('all')"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-bold transition-all {{ $statusFilter === 'all' ? 'bg-white text-indigo-800 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
            >
                <span>{{ __('All Entries') }}</span>
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-extrabold {{ $statusFilter === 'all' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-200 text-gray-700' }}">
                    {{ $counts['all'] }}
                </span>
            </button>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1 max-w-md">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search by product, barcode, invoice #, or ref...') }}"
                class="block w-full rounded-lg border-gray-300 pl-9 text-xs focus:border-indigo-500 focus:ring-indigo-500 py-2"
            >
            @if($search)
                <button
                    type="button"
                    wire:click="$set('search', '')"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

        <!-- Date & Per Page Filters -->
        <div class="flex items-center gap-2 flex-wrap">
            <select
                wire:model.live="dateFilter"
                class="rounded-lg border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500 py-2"
            >
                <option value="all">{{ __('All Dates') }}</option>
                <option value="today">{{ __('Today') }}</option>
                <option value="this_week">{{ __('This Week') }}</option>
                <option value="this_month">{{ __('This Month') }}</option>
            </select>

            <select
                wire:model.live="perPage"
                class="rounded-lg border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500 py-2"
            >
                <option value="15">15 {{ __('per page') }}</option>
                <option value="25">25 {{ __('per page') }}</option>
                <option value="50">50 {{ __('per page') }}</option>
                <option value="100">100 {{ __('per page') }}</option>
            </select>
        </div>
    </div>

    <!-- Data Table (Compact Accounting Ledger Layout) -->
    <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto min-h-[300px]">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-gray-100/80 text-gray-700 font-semibold text-[11px] uppercase tracking-wider border-b border-gray-200 select-none">
                    <tr>
                        <th scope="col" class="px-3 py-2 cursor-pointer hover:text-gray-900 transition-colors whitespace-nowrap" wire:click="sortBy('created_at')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Date') }}</span>
                                @if($sortField === 'created_at')
                                    <span class="text-indigo-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @else
                                    <span class="text-gray-400 font-normal">↕</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-3 py-2 whitespace-nowrap">{{ __('Sale Invoice / Ref') }}</th>
                        <th scope="col" class="px-3 py-2 whitespace-nowrap">{{ __('Barcode / SKU') }}</th>
                        <th scope="col" class="px-3 py-2 cursor-pointer hover:text-gray-900 transition-colors whitespace-nowrap" wire:click="sortBy('item_name')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Sold Item Name') }}</span>
                                @if($sortField === 'item_name')
                                    <span class="text-indigo-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @else
                                    <span class="text-gray-400 font-normal">↕</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-3 py-2 text-center whitespace-nowrap">{{ __('Qty') }}</th>
                        <th scope="col" class="px-3 py-2 text-right whitespace-nowrap">{{ __('Price / MRP') }}</th>
                        <th scope="col" class="px-3 py-2 text-right cursor-pointer hover:text-gray-900 transition-colors whitespace-nowrap" wire:click="sortBy('subtotal')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Subtotal') }}</span>
                                @if($sortField === 'subtotal')
                                    <span class="text-indigo-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @else
                                    <span class="text-gray-400 font-normal">↕</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-3 py-2 whitespace-nowrap">{{ __('Reconciliation Status / Master Link') }}</th>
                        <th scope="col" class="px-3 py-2 whitespace-nowrap">{{ __('Cashier') }}</th>
                        <th scope="col" class="px-3 py-2 text-center whitespace-nowrap">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white font-normal text-gray-800">
                    @forelse($reconciliations as $recon)
                        <tr class="hover:bg-blue-50/40 transition-colors h-9 {{ $recon->isPending() ? 'bg-amber-50/15' : 'odd:bg-white even:bg-gray-50/25' }}">
                            <!-- Date -->
                            @php
                                $dateTooltip = '<div class="font-bold text-white text-xs">' . e($recon->created_at->format('d M Y, h:i A')) . '</div>'
                                    . '<div class="text-[11px] text-gray-300 mt-0.5">Recorded on POS sale</div>';
                            @endphp
                            <td
                                class="px-3 py-1.5 whitespace-nowrap text-xs cursor-default text-gray-800 font-medium"
                                @mouseenter="showTooltip($event, {{ json_encode($dateTooltip) }})"
                                @mouseleave="hideTooltip()"
                                title="{{ $recon->created_at->format('d M Y, h:i A') }}"
                            >
                                {{ $recon->created_at->format('m/d/Y') }}
                            </td>

                            <!-- Sale Invoice & Reconciliation Ref -->
                            @php
                                $invoiceTooltip = '<div class="font-bold text-indigo-300 text-xs">Sale Invoice: ' . e($recon->sale?->invoice_number ?: '-') . '</div>';
                                if ($recon->sale) {
                                    $invoiceTooltip .= '<div class="text-[11px] text-gray-300 mt-0.5">Date: ' . e($recon->sale->sale_date?->format('d M Y') ?: '-') . ' | Status: ' . e(ucfirst($recon->sale->status?->value ?? $recon->sale->status)) . '</div>';
                                }
                                $invoiceTooltip .= '<div class="text-[11px] text-gray-200 mt-1 pt-1 border-t border-gray-700 font-mono">Ref: ' . e($recon->reconciliation_number) . '</div>';
                                $invoiceTooltip .= '<div class="text-[10px] text-gray-400">Created: ' . e($recon->created_at->format('d M Y, h:i A')) . '</div>';
                            @endphp
                            <td
                                class="px-3 py-1.5 whitespace-nowrap text-xs"
                                @mouseenter="showTooltip($event, {{ json_encode($invoiceTooltip) }})"
                                @mouseleave="hideTooltip()"
                            >
                                <div>
                                    @if($recon->sale)
                                        <a
                                            href="{{ route('sales.show', $recon->sale) }}"
                                            target="_blank"
                                            class="font-mono font-semibold text-indigo-600 hover:text-indigo-900 hover:underline inline-flex items-center gap-0.5"
                                            title="{{ __('View Sale Invoice ') }}{{ $recon->sale->invoice_number }}"
                                        >
                                            <span>{{ $recon->sale->invoice_number }}</span>
                                            <svg class="h-2.5 w-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                    @else
                                        <span class="text-gray-400 font-mono">-</span>
                                    @endif
                                </div>
                                <div class="mt-0.5">
                                    <span
                                        class="inline-block font-mono text-[10px] text-gray-700 bg-gray-100 px-1.5 py-0.2 rounded border border-gray-200 shadow-2xs"
                                        title="{{ $recon->reconciliation_number }} - {{ $recon->created_at->format('d M Y, h:i A') }}"
                                    >
                                        {{ $recon->reconciliation_number }}
                                    </span>
                                </div>
                            </td>
                            </td>

                            <!-- Barcode / SKU -->
                            @php
                                $displayBarcode = $recon->barcode ?: ($recon->matchedProduct?->product_code ?: $recon->matchedProduct?->sku);
                                $barcodeTooltip = '<div class="font-mono font-bold text-white text-xs">' . e($displayBarcode ?: 'No Barcode') . '</div>';
                                if ($recon->matchedProduct) {
                                    $barcodeTooltip .= '<div class="text-[11px] text-gray-300 mt-0.5">Catalog SKU: ' . e($recon->matchedProduct->sku) . ' | Code: ' . e($recon->matchedProduct->product_code ?: '-') . '</div>';
                                }
                            @endphp
                            <td
                                class="px-3 py-1.5 whitespace-nowrap font-mono text-xs cursor-default max-w-[130px] truncate"
                                @mouseenter="showTooltip($event, {{ json_encode($barcodeTooltip) }})"
                                @mouseleave="hideTooltip()"
                                title="{{ $displayBarcode ?: __('No barcode') }}"
                            >
                                @if($displayBarcode)
                                    <span class="text-gray-800">{{ $displayBarcode }}</span>
                                @else
                                    <span class="text-gray-400 italic text-[11px]">{{ __('No barcode') }}</span>
                                @endif
                            </td>

                            <!-- Sold Item Name -->
                            @php
                                $itemTooltip = '<div class="font-bold text-white text-xs mb-1">' . e($recon->item_name) . '</div>';
                                if ($recon->notes) {
                                    $itemTooltip .= '<div class="text-[11px] text-gray-300 font-normal leading-relaxed pt-1.5 border-t border-gray-700/80">' . nl2br(e($recon->notes)) . '</div>';
                                }
                            @endphp
                            <td
                                class="px-3 py-1.5 text-xs max-w-[220px] cursor-default"
                                @mouseenter="showTooltip($event, {{ json_encode($itemTooltip) }})"
                                @mouseleave="hideTooltip()"
                                title="{{ $recon->item_name }}{{ $recon->notes ? ' &#10;&#10;' . $recon->notes : '' }}"
                            >
                                <div class="flex items-center gap-1.5 truncate">
                                    <span class="font-semibold text-gray-900 truncate">{{ $recon->item_name }}</span>
                                    @if($recon->notes)
                                        <svg class="h-3 w-3 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                        </svg>
                                    @endif
                                </div>
                            </td>

                            <!-- Qty -->
                            <td class="px-3 py-1.5 text-center whitespace-nowrap font-semibold text-gray-900 text-xs">
                                {{ $recon->quantity }}
                            </td>

                            <!-- Price / MRP -->
                            @php
                                $priceTooltip = '<div class="text-xs font-semibold text-white">Price: ' . e(format_money($recon->unit_price)) . '</div>';
                                if ($recon->discount > 0) {
                                    $priceTooltip .= '<div class="text-[11px] text-red-300 mt-0.5">Discount: -' . e(format_money($recon->discount)) . '</div>';
                                }
                            @endphp
                            <td
                                class="px-3 py-1.5 text-right whitespace-nowrap text-gray-700 text-xs cursor-default"
                                @mouseenter="showTooltip($event, {{ json_encode($priceTooltip) }})"
                                @mouseleave="hideTooltip()"
                                title="Price: {{ format_money($recon->unit_price) }}{{ $recon->discount > 0 ? ' (Discount: -' . format_money($recon->discount) . ')' : '' }}"
                            >
                                <span>{{ format_money($recon->unit_price) }}</span>
                                @if($recon->discount > 0)
                                    <span class="text-[10px] text-red-600 font-medium ml-0.5">(-{{ format_money($recon->discount) }})</span>
                                @endif
                            </td>

                            <!-- Subtotal -->
                            <td class="px-3 py-1.5 text-right whitespace-nowrap font-bold text-indigo-700 text-xs">
                                {{ format_money($recon->subtotal) }}
                            </td>

                            <!-- Reconciliation Status / Master Link -->
                            <td class="px-3 py-1.5 text-xs whitespace-nowrap">
                                @if($recon->isPending())
                                    <!-- Top: PO Number -->
                                    <div>
                                        @if($recon->targetPurchase)
                                            <a
                                                href="{{ route('purchases.show', $recon->targetPurchase) }}"
                                                target="_blank"
                                                class="font-mono text-xs font-bold text-purple-700 hover:text-purple-900 hover:underline inline-flex items-center gap-0.5"
                                                title="{{ __('View Linked Purchase Order ') }}{{ $recon->targetPurchase->invoice_number }}"
                                            >
                                                <span>{{ $recon->targetPurchase->invoice_number }}</span>
                                                <svg class="h-2.5 w-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-400 font-mono">-</span>
                                        @endif
                                    </div>
                                    <!-- Below: Badge with Product Details On Hover -->
                                    @php
                                        $pendingBadgeTooltip = '<div class="font-bold text-white text-xs mb-1">Pending Action</div>';
                                        if ($recon->matchedProduct) {
                                            $pendingBadgeTooltip .= '<div class="text-[11px] text-blue-300"><span class="text-gray-400">Matched Product:</span> ' . e($recon->matchedProduct->name) . ' <span class="font-mono text-gray-400">(' . e($recon->matchedProduct->sku) . ')</span></div>';
                                        } else {
                                            $pendingBadgeTooltip .= '<div class="text-[11px] text-amber-300 font-medium">Not found in Master Catalog</div>';
                                        }
                                    @endphp
                                    <div class="mt-0.5">
                                        <span
                                            class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 shrink-0 cursor-default"
                                            @mouseenter="showTooltip($event, {{ json_encode($pendingBadgeTooltip) }})"
                                            @mouseleave="hideTooltip()"
                                            title="{{ $recon->matchedProduct ? 'Matched: ' . $recon->matchedProduct->name . ' (' . $recon->matchedProduct->sku . ')' : 'Not in Master Catalog' }}"
                                        >
                                            ● {{ __('Pending Action') }}
                                        </span>
                                    </div>
                                @elseif($recon->isReconciled())
                                    <!-- Top: PO Number -->
                                    <div>
                                        @if($recon->reconciledPurchase)
                                            <a
                                                href="{{ route('purchases.show', $recon->reconciledPurchase) }}"
                                                target="_blank"
                                                class="font-mono text-xs font-bold text-indigo-600 hover:text-indigo-900 hover:underline inline-flex items-center gap-0.5"
                                                title="{{ __('View Purchase Order ') }}{{ $recon->reconciledPurchase->invoice_number }}"
                                            >
                                                <span>{{ $recon->reconciledPurchase->invoice_number }}</span>
                                                <svg class="h-2.5 w-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-400 font-mono">-</span>
                                        @endif
                                    </div>
                                    <!-- Below: Badge with Product Details On Hover -->
                                    @php
                                        $reconciledBadgeTooltip = '<div class="font-bold text-emerald-400 text-xs mb-1">✓ Reconciled</div>';
                                        if ($recon->reconciledProduct) {
                                            $reconciledBadgeTooltip .= '<div class="text-[11px] text-gray-200"><span class="text-gray-400">Product:</span> ' . e($recon->reconciledProduct->name) . ' <span class="font-mono text-gray-400">(' . e($recon->reconciledProduct->sku) . ')</span></div>';
                                        } elseif ($recon->item_name) {
                                            $reconciledBadgeTooltip .= '<div class="text-[11px] text-gray-200"><span class="text-gray-400">Item:</span> ' . e($recon->item_name) . '</div>';
                                        }
                                        if ($recon->reconciler) {
                                            $reconciledBadgeTooltip .= '<div class="text-[10px] text-gray-400 mt-1">Reconciled by: <span class="text-gray-200 font-medium">' . e($recon->reconciler->name) . '</span></div>';
                                        }
                                        if ($recon->reconciled_at) {
                                            $reconciledBadgeTooltip .= '<div class="text-[10px] text-gray-400">' . e($recon->reconciled_at->format('d M Y, h:i A')) . '</div>';
                                        }
                                    @endphp
                                    <div class="mt-0.5">
                                        <span
                                            class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 shrink-0 cursor-default"
                                            @mouseenter="showTooltip($event, {{ json_encode($reconciledBadgeTooltip) }})"
                                            @mouseleave="hideTooltip()"
                                            title="Product: {{ $recon->reconciledProduct?->name ?: $recon->item_name }}"
                                        >
                                            ✓ {{ __('Reconciled') }}
                                        </span>
                                    </div>
                                @else
                                    <div class="mt-0.5">
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-gray-100 text-gray-700 shrink-0">
                                            {{ ucfirst($recon->status) }}
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <!-- Cashier -->
                            <td
                                class="px-3 py-1.5 whitespace-nowrap text-gray-600 text-xs truncate max-w-[100px] cursor-default"
                                title="Cashier: {{ $recon->creator?->name ?: '-' }}"
                            >
                                {{ $recon->creator?->name ?: '-' }}
                            </td>

                            <!-- Actions -->
                            <td class="px-3 py-1 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    @if($recon->isPending())
                                        @if($recon->matched_product_id)
                                            <a
                                                href="{{ route('purchases.create', ['recon_ids' => $recon->id]) }}"
                                                class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[11px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 transition-colors"
                                                title="{{ __('Create Purchase Order for this item') }}"
                                            >
                                                <svg class="h-3 w-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                <span>{{ __('+PO') }}</span>
                                            </a>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="openReconcileModal({{ $recon->id }}, 'create')"
                                                class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[11px] font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 transition-colors"
                                                title="{{ __('Create this item in Master Catalog and draft PO') }}"
                                            >
                                                <svg class="h-3 w-3 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                <span>{{ __('+Master') }}</span>
                                            </button>
                                        @endif

                                        <button
                                            type="button"
                                            wire:click="openReconcileModal({{ $recon->id }}, 'po')"
                                            class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[11px] font-bold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-2xs"
                                            title="{{ __('Open reconciliation options') }}"
                                        >
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            <span>{{ __('Reconcile') }}</span>
                                        </button>

                                        @if($recon->barcode || $recon->matched_product_id)
                                            <button
                                                type="button"
                                                wire:click="autoReconcileSingle({{ $recon->id }})"
                                                class="inline-flex items-center justify-center p-1 rounded text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors"
                                                title="{{ __('Quick auto-reconcile with barcode match') }}"
                                            >
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                            </button>
                                        @endif
                                    @endif

                                    <button
                                        type="button"
                                        wire:click="openEditModal({{ $recon->id }})"
                                        class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[11px] font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 border border-gray-300 transition-colors"
                                        title="{{ __('Edit details of this reconciliation entry') }}"
                                    >
                                        <svg class="h-3 w-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                        <span>{{ __('Edit') }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-gray-500">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <svg class="h-10 w-10 text-gray-300 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-sm font-semibold text-gray-700">{{ __('No reconciliation records found') }}</p>
                                    <p class="text-xs text-gray-400">
                                        @if($statusFilter === 'pending')
                                            {{ __('All manual POS items have been reconciled! Any future manual sale entries will appear here.') }}
                                        @else
                                            {{ __('No entries match the selected filters or search query.') }}
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($reconciliations->hasPages())
            <div class="px-4 py-2 border-t border-gray-200 bg-gray-50 sm:px-6">
                {{ $reconciliations->links() }}
            </div>
        @endif
    </div>

    <!-- Floating Full-Text Tooltip -->
    <div
        x-show="tooltipVisible"
        x-cloak
        :style="`top: ${tooltipY}px; left: ${tooltipX}px; transform: ${tooltipPlacement === 'top' ? 'translateY(-100%)' : 'none'};`"
        class="fixed z-50 max-w-sm sm:max-w-md p-2.5 bg-gray-900/95 backdrop-blur-xs text-white text-xs rounded-lg shadow-xl pointer-events-none whitespace-normal break-words border border-gray-700 leading-relaxed transition-opacity"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        <div x-html="tooltipText"></div>
    </div>
</div>
