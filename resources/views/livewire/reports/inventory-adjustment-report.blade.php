<div class="space-y-6">
    <!-- Controls Header: Date Filter & Actions -->
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs sm:p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Date Presets & Inputs -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-wrap">
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50/70 p-1">
                    @foreach([
                        'today' => __('Today'),
                        'yesterday' => __('Yesterday'),
                        'this_week' => __('This Week'),
                        'this_month' => __('This Month'),
                        'last_month' => __('Last Month'),
                        'all_time' => __('All Time'),
                    ] as $key => $label)
                        <button
                            type="button"
                            wire:click="$set('dateFilter', '{{ $key }}')"
                            class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors {{ $dateFilter === $key ? 'bg-white font-bold text-gray-900 shadow-xs' : 'text-gray-600 hover:text-gray-900' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span class="font-medium">{{ __('From:') }}</span>
                    <input
                        type="date"
                        wire:model.live="startDate"
                        class="rounded-md border-gray-300 py-1 px-2 text-xs focus:border-blue-500 focus:ring-blue-500 shadow-xs"
                    >
                    <span class="font-medium">{{ __('To:') }}</span>
                    <input
                        type="date"
                        wire:model.live="endDate"
                        class="rounded-md border-gray-300 py-1 px-2 text-xs focus:border-blue-500 focus:ring-blue-500 shadow-xs"
                    >
                </div>
            </div>

            <!-- Export Actions -->
            <div class="flex items-center gap-2 self-end lg:self-auto">
                <a
                    href="{{ route('reports.adjustments.csv', ['start_date' => $startDate, 'end_date' => $endDate, 'type' => $typeFilter, 'location' => $locationFilter, 'reason' => $reasonFilter, 'search' => $search]) }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition-colors"
                >
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4 text-emerald-600" />
                    <span>{{ __('Export CSV') }}</span>
                </a>

                <a
                    href="{{ route('reports.adjustments.pdf', ['start_date' => $startDate, 'end_date' => $endDate, 'type' => $typeFilter, 'location' => $locationFilter, 'reason' => $reasonFilter, 'search' => $search]) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-blue-500 transition-colors"
                >
                    <x-heroicon-o-printer class="w-4 h-4" />
                    <span>{{ __('Print / PDF') }}</span>
                </a>
            </div>
        </div>

        <!-- Mode / Type Filter Pills -->
        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-semibold uppercase text-gray-500 tracking-wider">{{ __('Show:') }}</span>
                <button
                    type="button"
                    wire:click="$set('typeFilter', 'all')"
                    class="px-3 py-1 text-xs rounded-full font-semibold border transition-all {{ $typeFilter === 'all' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                >
                    {{ __('All Damage & Surplus') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('typeFilter', 'quantity_decrease')"
                    class="px-3 py-1 text-xs rounded-full font-semibold border transition-all {{ $typeFilter === 'quantity_decrease' ? 'bg-red-600 border-red-600 text-white' : 'bg-white border-red-200 text-red-700 hover:bg-red-50' }}"
                >
                    {{ __('Write-Off / Damage (Loss)') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('typeFilter', 'quantity_increase')"
                    class="px-3 py-1 text-xs rounded-full font-semibold border transition-all {{ $typeFilter === 'quantity_increase' ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}"
                >
                    {{ __('Surplus / Found (Gain)') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('typeFilter', 'all_types')"
                    class="px-3 py-1 text-xs rounded-full font-semibold border transition-all {{ $typeFilter === 'all_types' ? 'bg-purple-600 border-purple-600 text-white' : 'bg-white border-purple-200 text-purple-700 hover:bg-purple-50' }}"
                >
                    {{ __('All Adjustments (+ Transfers & Splits)') }}
                </button>
            </div>

            <div class="text-xs text-gray-500">
                <span>{{ __('Period:') }}</span>
                <span class="font-bold text-gray-900">{{ Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ Carbon\Carbon::parse($endDate)->format('d M Y') }}</span>
            </div>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Write-off / Damage Loss Card -->
        <div class="rounded-xl border border-red-200 bg-linear-to-br from-red-50/50 to-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-red-700">{{ __('Write-Off / Damage Loss') }}</span>
                <span class="rounded-md bg-red-100 p-1.5 text-red-600">
                    <x-heroicon-o-arrow-trending-down class="w-5 h-5" />
                </span>
            </div>
            <p class="mt-3 text-2xl font-black font-mono text-red-600">
                -₹{{ number_format($kpis['damage_amount'], 2) }}
            </p>
            <div class="mt-2 flex items-center justify-between text-xs text-red-800/80 border-t border-red-100 pt-2">
                <span>{{ __('Units Written Off:') }} <strong class="text-red-900 font-mono">{{ $kpis['damage_units'] }}</strong></span>
                <span><strong class="font-mono">{{ $kpis['damage_count'] }}</strong> {{ __('entries') }}</span>
            </div>
        </div>

        <!-- 2. Surplus / Found Gain Card -->
        <div class="rounded-xl border border-emerald-200 bg-linear-to-br from-emerald-50/50 to-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">{{ __('Surplus / Found Gain') }}</span>
                <span class="rounded-md bg-emerald-100 p-1.5 text-emerald-600">
                    <x-heroicon-o-arrow-trending-up class="w-5 h-5" />
                </span>
            </div>
            <p class="mt-3 text-2xl font-black font-mono text-emerald-600">
                +₹{{ number_format($kpis['surplus_amount'], 2) }}
            </p>
            <div class="mt-2 flex items-center justify-between text-xs text-emerald-800/80 border-t border-emerald-100 pt-2">
                <span>{{ __('Units Added:') }} <strong class="text-emerald-900 font-mono">{{ $kpis['surplus_units'] }}</strong></span>
                <span><strong class="font-mono">{{ $kpis['surplus_count'] }}</strong> {{ __('entries') }}</span>
            </div>
        </div>

        <!-- 3. Net Adjustment Value Impact Card -->
        <div class="rounded-xl border border-blue-200 bg-linear-to-br from-blue-50/50 to-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-blue-800">{{ __('Net Value Impact') }}</span>
                <span class="rounded-md bg-blue-100 p-1.5 text-blue-700">
                    <x-heroicon-o-scale class="w-5 h-5" />
                </span>
            </div>
            <p class="mt-3 text-2xl font-black font-mono {{ $kpis['net_amount'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                {{ $kpis['net_amount'] >= 0 ? '+' : '' }}₹{{ number_format($kpis['net_amount'], 2) }}
            </p>
            <div class="mt-2 flex items-center justify-between text-xs text-blue-900/80 border-t border-blue-100 pt-2">
                <span>{{ __('Net Units Difference:') }} <strong class="font-mono {{ $kpis['net_units'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $kpis['net_units'] > 0 ? '+' : '' }}{{ $kpis['net_units'] }}</strong></span>
                <span class="text-gray-500">{{ __('Surplus - Loss') }}</span>
            </div>
        </div>

        <!-- 4. Gross Total Adjustments Posted Card -->
        <div class="rounded-xl border border-purple-200 bg-linear-to-br from-purple-50/50 to-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-purple-800">{{ __('Total Adjustments Posted') }}</span>
                <span class="rounded-md bg-purple-100 p-1.5 text-purple-700">
                    <x-heroicon-o-document-chart-bar class="w-5 h-5" />
                </span>
            </div>
            <p class="mt-3 text-2xl font-black font-mono text-purple-900">
                ₹{{ number_format($kpis['gross_amount'], 2) }}
            </p>
            <div class="mt-2 flex items-center justify-between text-xs text-purple-900/80 border-t border-purple-100 pt-2">
                <span>{{ __('Total Transactions:') }} <strong class="text-purple-950 font-mono">{{ $kpis['total_count'] }}</strong></span>
                <span class="text-gray-500">{{ __('Gross Volume') }}</span>
            </div>
        </div>
    </div>

    <!-- Reason Breakdown Section -->
    @if(!empty($kpis['reason_breakdown']) && count($kpis['reason_breakdown']) > 0)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-blue-600"></span>
                    {{ __('Adjustment Reasons Breakdown') }}
                </h3>
                <span class="text-xs text-gray-500">{{ __('Breakdown of posted amounts by reason code') }}</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                @foreach($kpis['reason_breakdown'] as $rb)
                    <div class="p-2.5 rounded-lg border border-gray-100 bg-gray-50/70 hover:bg-gray-100/70 transition-colors">
                        <div class="flex items-center justify-between text-[11px] text-gray-500">
                            <span class="truncate font-medium" title="{{ $rb['label'] }}">{{ $rb['label'] }}</span>
                            <span class="font-mono text-gray-600">{{ $rb['count'] }}</span>
                        </div>
                        <p class="text-sm font-bold font-mono text-gray-900 mt-1">₹{{ number_format($rb['amount'], 2) }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">{{ $rb['units'] }} {{ __('unit(s)') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Main Data Table Container -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <!-- Table Toolbar -->
        <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="flex items-center gap-2 flex-1 max-w-md">
                <div class="relative w-full">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search product, barcode, SKU, adjustment #..."
                        class="block w-full rounded-md border-gray-300 pl-9 pr-3 py-1.5 text-xs focus:border-blue-500 focus:ring-blue-500 shadow-xs"
                    >
                </div>
                @if($search)
                    <button
                        type="button"
                        wire:click="$set('search', '')"
                        class="text-xs text-gray-500 hover:text-gray-700 underline"
                    >
                        {{ __('Clear') }}
                    </button>
                @endif
            </div>

            <div class="flex items-center gap-2 flex-wrap text-xs">
                <!-- Location Filter -->
                <select
                    wire:model.live="locationFilter"
                    class="rounded-md border-gray-300 py-1.5 px-2 text-xs focus:border-blue-500 focus:ring-blue-500 shadow-xs"
                >
                    <option value="all">{{ __('All Locations') }}</option>
                    <option value="shop">{{ __('Store / Shop') }}</option>
                    <option value="parlour">{{ __('Back Parlour') }}</option>
                </select>

                <!-- Reason Filter -->
                <select
                    wire:model.live="reasonFilter"
                    class="rounded-md border-gray-300 py-1.5 px-2 text-xs focus:border-blue-500 focus:ring-blue-500 shadow-xs"
                >
                    <option value="all">{{ __('All Reasons') }}</option>
                    <option value="damaged">{{ __('Damaged Goods') }}</option>
                    <option value="expired">{{ __('Expired Stock') }}</option>
                    <option value="theft_loss">{{ __('Theft / Shrinkage') }}</option>
                    <option value="count_correction">{{ __('Count Discrepancy') }}</option>
                    <option value="found_correction">{{ __('Found Goods / Surplus') }}</option>
                    <option value="supplier_bonus">{{ __('Supplier Free Samples / Bonus') }}</option>
                    <option value="misallocation_split">{{ __('PO Misallocation / Split') }}</option>
                    <option value="transfer_to_shop">{{ __('Transfer to Store / Shop') }}</option>
                    <option value="other">{{ __('Other') }}</option>
                </select>

                <!-- Per Page -->
                <select
                    wire:model.live="perPage"
                    class="rounded-md border-gray-300 py-1.5 px-2 text-xs focus:border-blue-500 focus:ring-blue-500 shadow-xs"
                >
                    <option value="15">15 {{ __('per page') }}</option>
                    <option value="25">25 {{ __('per page') }}</option>
                    <option value="50">50 {{ __('per page') }}</option>
                    <option value="100">100 {{ __('per page') }}</option>
                </select>

                <button
                    type="button"
                    wire:click="resetFilters"
                    class="px-2.5 py-1.5 rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-xs transition-colors"
                >
                    {{ __('Reset') }}
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50 text-gray-600 font-semibold uppercase tracking-wider">
                    <tr>
                        <th scope="col" class="py-3 px-3.5 text-left cursor-pointer hover:text-gray-900" wire:click="sortBy('created_at')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Date & Time') }}</span>
                                @if($sortField === 'created_at')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3 px-3.5 text-left cursor-pointer hover:text-gray-900" wire:click="sortBy('adjustment_number')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Adjustment #') }}</span>
                                @if($sortField === 'adjustment_number')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3 px-3.5 text-left">{{ __('Type') }}</th>
                        <th scope="col" class="py-3 px-3.5 text-left">{{ __('Product') }}</th>
                        <th scope="col" class="py-3 px-3.5 text-center">{{ __('Location') }}</th>
                        <th scope="col" class="py-3 px-3.5 text-left">{{ __('Batch / Ref') }}</th>
                        <th scope="col" class="py-3 px-3.5 text-left">{{ __('Reason') }}</th>
                        <th scope="col" class="py-3 px-3.5 text-right cursor-pointer hover:text-gray-900" wire:click="sortBy('quantity')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Quantity') }}</span>
                                @if($sortField === 'quantity')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3 px-3.5 text-right cursor-pointer hover:text-gray-900" wire:click="sortBy('unit_cost')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Unit Cost (₹)') }}</span>
                                @if($sortField === 'unit_cost')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3 px-3.5 text-right cursor-pointer hover:text-gray-900" wire:click="sortBy('total_amount')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Total Amount (₹)') }}</span>
                                @if($sortField === 'total_amount')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3 px-3.5 text-left">{{ __('Posted By') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($adjustments as $adj)
                        @php
                            $isDamage = $adj->type === 'quantity_decrease';
                            $isSurplus = $adj->type === 'quantity_increase';
                            $isReallocation = $adj->type === 'reallocation';
                            $isTransfer = $adj->type === 'location_transfer';
                            $totalVal = (int) $adj->quantity * (float) $adj->unit_cost;
                            $loc = ($adj->source_location?->value ?? ($adj->target_location?->value ?? 'shop'));
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="py-3 px-3.5 whitespace-nowrap text-gray-600">
                                <div>{{ $adj->created_at?->format('d/m/Y') ?: '-' }}</div>
                                <div class="text-[10px] text-gray-400">{{ $adj->created_at?->format('h:i A') ?: '' }}</div>
                            </td>
                            <td class="py-3 px-3.5 whitespace-nowrap font-mono font-medium text-gray-900">
                                {{ $adj->adjustment_number }}
                            </td>
                            <td class="py-3 px-3.5 whitespace-nowrap">
                                @if($isDamage)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                        {{ __('Write-Off / Damage') }}
                                    </span>
                                @elseif($isSurplus)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        {{ __('Surplus / Found') }}
                                    </span>
                                @elseif($isReallocation)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                                        {{ __('Reallocation') }}
                                    </span>
                                @elseif($isTransfer)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-[11px] font-semibold text-purple-700">
                                        {{ __('Location Movement') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-semibold text-gray-700">
                                        {{ ucfirst((string) $adj->type) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3.5">
                                <div class="font-semibold text-gray-900">{{ $adj->sourceProduct?->name ?: __('N/A') }}</div>
                                <div class="text-[11px] text-gray-500 font-mono flex items-center gap-2">
                                    @if($adj->sourceProduct?->product_code)
                                        <span>Code: {{ $adj->sourceProduct->product_code }}</span>
                                    @endif
                                    @if($adj->sourceProduct?->sku)
                                        <span>SKU: {{ $adj->sourceProduct->sku }}</span>
                                    @endif
                                </div>
                                @if($isReallocation && $adj->targetProduct)
                                    <div class="text-[10px] text-emerald-700 font-medium mt-0.5">
                                        ↳ {{ __('To:') }} {{ $adj->targetProduct->name }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                @if($loc === 'parlour')
                                    <span class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-[11px] font-semibold text-purple-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                        {{ __('Parlour') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                        {{ __('Store') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3.5 whitespace-nowrap text-gray-600 font-mono">
                                @if($adj->sourcePurchaseItem?->batch_number)
                                    <span>{{ $adj->sourcePurchaseItem->batch_number }}</span>
                                    @if($adj->sourcePurchaseItem?->purchase)
                                        <div class="text-[10px] text-gray-400 font-sans">PO: {{ $adj->sourcePurchaseItem->purchase->invoice_number }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-3.5 whitespace-nowrap text-gray-700">
                                <div>{{ app(\App\Services\InventoryAdjustmentReportService::class)->formatReasonLabel($adj->reason) }}</div>
                                @if($adj->notes)
                                    <div class="text-[10px] text-gray-400 max-w-xs truncate" title="{{ $adj->notes }}">{{ $adj->notes }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-3.5 whitespace-nowrap text-right font-mono font-bold">
                                @if($isDamage)
                                    <span class="text-red-600">-{{ $adj->quantity }}</span>
                                @elseif($isSurplus)
                                    <span class="text-emerald-600">+{{ $adj->quantity }}</span>
                                @else
                                    <span class="text-gray-900">{{ $adj->quantity }}</span>
                                @endif
                                <span class="text-[10px] text-gray-400 font-normal ml-0.5">{{ $adj->sourceProduct?->unit?->symbol ?: __('units') }}</span>
                            </td>
                            <td class="py-3 px-3.5 whitespace-nowrap text-right font-mono text-gray-800">
                                ₹{{ number_format((float) $adj->unit_cost, 2) }}
                            </td>
                            <td class="py-3 px-3.5 whitespace-nowrap text-right font-mono font-bold text-sm">
                                @if($isDamage)
                                    <span class="text-red-600">-₹{{ number_format($totalVal, 2) }}</span>
                                @elseif($isSurplus)
                                    <span class="text-emerald-600">+₹{{ number_format($totalVal, 2) }}</span>
                                @else
                                    <span class="text-gray-900">₹{{ number_format($totalVal, 2) }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-3.5 whitespace-nowrap text-gray-600">
                                <div>{{ $adj->creator?->name ?: __('System') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-12 text-center text-gray-500">
                                <x-heroicon-o-document-chart-bar class="w-10 h-10 mx-auto text-gray-300 mb-2" />
                                <p class="text-sm font-semibold text-gray-700">{{ __('No inventory adjustments found.') }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ __('Try adjusting the date range, filters, or search keyword.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        @if($adjustments->hasPages())
            <div class="p-3 border-t border-gray-100 bg-gray-50/50">
                {{ $adjustments->links() }}
            </div>
        @endif
    </div>
</div>
