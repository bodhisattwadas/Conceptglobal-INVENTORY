<div class="space-y-4">
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

        <!-- Bulk Auto-Reconcile Button -->
        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="autoReconcileAllMatched"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors disabled:opacity-50"
                title="{{ __('Attempt to automatically match and reconcile all pending items having a matching barcode or name in product master') }}"
            >
                <svg wire:loading.remove wire:target="autoReconcileAllMatched" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                </svg>
                <svg wire:loading wire:target="autoReconcileAllMatched" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ __('Auto-Reconcile Matched') }}</span>
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

    <!-- Bulk Selection Toolbar -->
    @if(count($selectedIds) > 0)
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-indigo-900 shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="font-bold bg-indigo-600 text-white rounded-full h-6 w-6 flex items-center justify-center text-xs shadow-xs">
                    {{ count($selectedIds) }}
                </span>
                <span class="font-semibold text-indigo-950">
                    {{ count($selectedIds) }} {{ __('pending item(s) selected') }}
                </span>
                <span class="text-indigo-500">•</span>
                <span class="text-indigo-700">
                    {{ __('Generate a Purchase Order to order these items from a vendor and auto-reconcile once received.') }}
                </span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    wire:click="createPurchaseOrderForSelected"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-sm transition-colors text-xs"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>{{ __('Create PO for Selected') }}</span>
                </button>
                <button
                    type="button"
                    wire:click="$set('selectedIds', [])"
                    class="text-xs text-indigo-700 hover:text-indigo-950 underline font-medium px-2 py-1"
                >
                    {{ __('Clear selection') }}
                </button>
            </div>
        </div>
    @endif

    <!-- Data Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto min-h-[300px]">
            <table class="w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50 text-gray-500 font-semibold uppercase tracking-wider">
                    <tr>
                        <th scope="col" class="w-10 px-3 py-3 text-center">
                            <input
                                type="checkbox"
                                wire:model.live="selectAll"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                title="{{ __('Select all pending') }}"
                            >
                        </th>
                        <th scope="col" class="px-4 py-3 cursor-pointer hover:text-gray-700" wire:click="sortBy('created_at')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Date / Ref') }}</span>
                                @if($sortField === 'created_at')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3">{{ __('Sale Invoice') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Barcode / SKU') }}</th>
                        <th scope="col" class="px-4 py-3 cursor-pointer hover:text-gray-700" wire:click="sortBy('item_name')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Sold Item Name') }}</span>
                                @if($sortField === 'item_name')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-4 py-3 text-center">{{ __('Qty') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Price / MRP') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('Subtotal') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Reconciliation Status / Master Link') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Cashier') }}</th>
                        <th scope="col" class="px-4 py-3 text-center">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($reconciliations as $recon)
                        <tr class="hover:bg-indigo-50/30 transition-colors {{ $recon->isPending() ? 'bg-amber-50/20' : '' }}">
                            <!-- Checkbox -->
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                @if($recon->isPending())
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedIds"
                                        value="{{ $recon->id }}"
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                    >
                                @else
                                    <span class="text-gray-300">-</span>
                                @endif
                            </td>

                            <!-- Date / Ref -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-mono font-bold text-gray-900">{{ $recon->reconciliation_number }}</div>
                                <div class="text-[11px] text-gray-500">{{ $recon->created_at->format('d M Y, h:i A') }}</div>
                            </td>

                            <!-- Sale Invoice -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($recon->sale)
                                    <a
                                        href="{{ route('sales.show', $recon->sale) }}"
                                        target="_blank"
                                        class="font-mono font-bold text-indigo-600 hover:text-indigo-900 hover:underline flex items-center gap-1"
                                    >
                                        <span>{{ $recon->sale->invoice_number }}</span>
                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>

                            <!-- Barcode / SKU -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($recon->barcode)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded font-mono text-[11px] font-semibold bg-gray-100 text-gray-800 border border-gray-200">
                                        {{ $recon->barcode }}
                                    </span>
                                @else
                                    <span class="text-gray-400 italic">{{ __('No barcode') }}</span>
                                @endif
                            </td>

                            <!-- Item Name -->
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-900">{{ $recon->item_name }}</div>
                                @if($recon->notes)
                                    <div class="text-[11px] text-gray-500 mt-0.5 truncate max-w-xs" title="{{ $recon->notes }}">
                                        {{ $recon->notes }}
                                    </div>
                                @endif
                            </td>

                            <!-- Qty -->
                            <td class="px-4 py-3 text-center whitespace-nowrap font-bold text-gray-900">
                                {{ $recon->quantity }}
                            </td>

                            <!-- Unit Price -->
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-gray-700">
                                {{ format_money($recon->unit_price) }}
                                @if($recon->discount > 0)
                                    <div class="text-[10px] text-red-600">-{{ format_money($recon->discount) }}</div>
                                @endif
                            </td>

                            <!-- Subtotal -->
                            <td class="px-4 py-3 text-right whitespace-nowrap font-bold text-indigo-700">
                                {{ format_money($recon->subtotal) }}
                            </td>

                            <!-- Reconciliation Status & Product Link -->
                            <td class="px-4 py-3">
                                <div class="space-y-1">
                                    @if($recon->isPending())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                            ● {{ __('Pending Action') }}
                                        </span>
                                        @if($recon->matchedProduct)
                                            <div class="text-[11px] text-blue-700 font-medium">
                                                Matched: {{ $recon->matchedProduct->name }} ({{ $recon->matchedProduct->sku }})
                                            </div>
                                        @else
                                            <div class="text-[10px] text-amber-600 font-medium">
                                                {{ __('Not in Master Catalog') }}
                                            </div>
                                        @endif
                                        @if($recon->targetPurchase)
                                            <div class="text-[11px] text-purple-700 font-medium flex items-center gap-1 mt-0.5">
                                                <span>PO: {{ $recon->targetPurchase->po_reference }}</span>
                                                <span class="text-[9px] px-1 py-0.2 rounded bg-purple-100 text-purple-800 font-bold uppercase">{{ $recon->targetPurchase->status }}</span>
                                            </div>
                                        @endif
                                    @elseif($recon->isReconciled())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            ✓ {{ __('Reconciled') }}
                                        </span>
                                        @if($recon->reconciledProduct)
                                            <div class="text-[11px] text-gray-700 font-medium">
                                                Product: {{ $recon->reconciledProduct->name }} ({{ $recon->reconciledProduct->sku }})
                                            </div>
                                        @endif
                                        @if($recon->reconciledPurchase)
                                            <div class="text-[11px] text-indigo-700 font-medium">
                                                From PO: {{ $recon->reconciledPurchase->po_reference }}
                                            </div>
                                        @endif
                                        @if($recon->reconciled_at)
                                            <div class="text-[10px] text-gray-400">
                                                {{ $recon->reconciled_at->format('d M, h:i A') }} {{ $recon->reconciler ? 'by ' . $recon->reconciler->name : '' }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-700">
                                            {{ ucfirst($recon->status) }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Cashier -->
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                {{ $recon->creator?->name ?: '-' }}
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    @if($recon->isPending())
                                        @if($recon->matched_product_id)
                                            <!-- PO Shortcut Button -->
                                            <a
                                                href="{{ route('purchases.create', ['recon_ids' => $recon->id]) }}"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 transition-colors shadow-2xs"
                                                title="{{ __('Create Purchase Order for this item') }}"
                                            >
                                                <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                <span>{{ __('+ PO') }}</span>
                                            </a>
                                        @else
                                            <!-- Create in Master Shortcut -->
                                            <button
                                                type="button"
                                                wire:click="openReconcileModal({{ $recon->id }}, 'create')"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 transition-colors shadow-2xs"
                                                title="{{ __('Create this item in Master Catalog and draft PO') }}"
                                            >
                                                <svg class="h-3.5 w-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                <span>{{ __('+ Master') }}</span>
                                            </button>
                                        @endif

                                        <!-- Reconcile Button -->
                                        <button
                                            type="button"
                                            wire:click="openReconcileModal({{ $recon->id }}, 'po')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm"
                                            title="{{ __('Open reconciliation options') }}"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            <span>{{ __('Reconcile') }}</span>
                                        </button>

                                        <!-- Quick Auto Button if barcode present -->
                                        @if($recon->barcode || $recon->matched_product_id)
                                            <button
                                                type="button"
                                                wire:click="autoReconcileSingle({{ $recon->id }})"
                                                class="inline-flex items-center justify-center p-1.5 rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors"
                                                title="{{ __('Quick auto-reconcile with barcode match') }}"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                            </button>
                                        @endif
                                    @endif

                                    <!-- Edit Button (Always available for ALL edit options) -->
                                    <button
                                        type="button"
                                        wire:click="openEditModal({{ $recon->id }})"
                                        class="inline-flex items-center gap-1 px-2 py-1.5 rounded-md text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 border border-gray-300 transition-colors"
                                        title="{{ __('Edit details of this reconciliation entry') }}"
                                    >
                                        <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                        <span>{{ __('Edit') }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-6 py-12 text-center text-gray-500">
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
            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 sm:px-6">
                {{ $reconciliations->links() }}
            </div>
        @endif
    </div>
</div>
