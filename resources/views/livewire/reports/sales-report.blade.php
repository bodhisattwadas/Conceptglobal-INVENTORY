<div class="space-y-6">
    <!-- Filter and Action Bar (Matched to Dashboard Overview Header) -->
    <div class="bg-card border border-border rounded-xl p-4 sm:p-5 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-chart-bar class="w-5 h-5 text-primary" />
                    <span>{{ __('Overview') }}</span>
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ __('Monitor your sales performance, profit margins, and collections at a glance.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Period Selector -->
                <select
                    id="dateFilter"
                    wire:model.live="dateFilter"
                    class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm font-medium text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                >
                    <option value="today">{{ __('Today') }}</option>
                    <option value="yesterday">{{ __('Yesterday') }}</option>
                    <option value="this_week">{{ __('This Week') }}</option>
                    <option value="this_month">{{ __('This Month') }}</option>
                    <option value="last_month">{{ __('Last Month') }}</option>
                    <option value="this_year">{{ __('This Year') }}</option>
                    <option value="all_time">{{ __('All Time') }}</option>
                    <option value="custom">{{ __('Custom Range') }}</option>
                </select>

                <!-- Custom Range Inputs -->
                <div class="flex items-center gap-2">
                    <input
                        type="date"
                        wire:model.live="startDate"
                        class="h-9 rounded-md border border-input bg-background px-2.5 py-1 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <span class="text-xs text-muted-foreground">{{ __('to') }}</span>
                    <input
                        type="date"
                        wire:model.live="endDate"
                        class="h-9 rounded-md border border-input bg-background px-2.5 py-1 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </div>

                <!-- Export & Print Buttons -->
                <button
                    type="button"
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    class="print:hidden inline-flex items-center gap-1.5 rounded-md border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3 text-sm font-medium shadow-sm transition-colors disabled:opacity-50"
                >
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    <span wire:loading.remove wire:target="exportCsv">{{ __('Export CSV') }}</span>
                    <span wire:loading wire:target="exportCsv">{{ __('Exporting...') }}</span>
                </button>

                <a
                    href="{{ route('reports.sales.pdf', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                    class="print:hidden inline-flex items-center gap-1.5 rounded-md bg-primary text-primary-foreground hover:bg-primary/90 h-9 px-3 text-sm font-medium shadow-sm transition-colors"
                >
                    <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                    <span>{{ __('PDF') }}</span>
                </a>

                <a
                    href="{{ route('reports.sales.pdf', ['start_date' => $startDate, 'end_date' => $endDate, 'stream' => 1]) }}"
                    target="_blank"
                    class="print:hidden inline-flex items-center gap-1.5 rounded-md border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3 text-sm font-medium shadow-sm transition-colors"
                >
                    <x-heroicon-o-printer class="w-4 h-4" />
                    <span>{{ __('Print') }}</span>
                </a>
            </div>
        </div>

        <!-- Date Range Status Subtitle -->
        <div class="text-xs text-muted-foreground flex items-center gap-2 pt-2 border-t border-border">
            <x-heroicon-o-calendar class="w-4 h-4 text-primary" />
            <span>
                {{ __('Showing reports from') }}
                <strong class="text-foreground">{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}</strong>
                {{ __('to') }}
                <strong class="text-foreground">{{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</strong>
            </span>
            <span class="ml-auto font-medium text-foreground">
                {{ $kpis['total_orders'] }} {{ \Illuminate\Support\Str::plural('transaction', $kpis['total_orders']) }}
            </span>
        </div>
    </div>

    <!-- Stats Grid (Matched to Dashboard Names & Visual Style) -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <!-- Total Sales (Matched to Dashboard Total Sales) -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md" style="background-color: #eff6ff; border-color: #bfdbfe; color: #172554;">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">{{ __('Total Sales') }}</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #dbeafe; color: #1d4ed8;">
                    <x-heroicon-o-banknotes class="h-4 w-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">
                    ₹ {{ number_format($kpis['net_sales'], 2) }}
                </div>
                <div class="flex items-center justify-between text-xs mt-1" style="color: rgba(29, 78, 216, 0.8);">
                    <span>{{ $kpis['total_orders'] }} {{ \Illuminate\Support\Str::plural('transaction', $kpis['total_orders']) }}</span>
                </div>
            </div>
        </div>

        <!-- Gross Profit (Matched to Dashboard Gross Profit) -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md" style="background-color: #ecfdf5; border-color: #a7f3d0; color: #064e3b;">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">{{ __('Gross Profit') }}</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #d1fae5; color: #047857;">
                    <x-heroicon-o-arrow-trending-up class="h-4 w-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold {{ $kpis['gross_profit'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                    ₹ {{ number_format($kpis['gross_profit'], 2) }}
                </div>
                <p class="text-xs mt-1 font-medium" style="color: rgba(4, 120, 87, 0.9);">
                    @if($kpis['net_sales'] > 0)
                        {{ number_format((float) $kpis['margin'], 1) }}% margin (COGS: ₹ {{ number_format($kpis['cogs'], 2) }})
                    @else
                        {{ __('Estimated based on COGS') }}
                    @endif
                </p>
            </div>
        </div>

        <!-- Gross Sales -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md" style="background-color: #f0fdfa; border-color: #99f6e4; color: #134e4a;">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">{{ __('Gross Sales') }}</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #ccfbf1; color: #0f766e;">
                    <x-heroicon-o-shopping-bag class="h-4 w-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">
                    ₹ {{ number_format($kpis['gross_sales'], 2) }}
                </div>
                <p class="text-xs mt-1" style="color: rgba(15, 118, 110, 0.8);">
                    {{ __('Pre-discount retail total') }}
                </p>
            </div>
        </div>

        <!-- Total Discounts -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md" style="background-color: #fffbeb; border-color: #fde68a; color: #78350f;">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">{{ __('Total Discounts') }}</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #fef3c7; color: #b45309;">
                    <x-heroicon-o-tag class="h-4 w-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold text-amber-600">
                    ₹ {{ number_format($kpis['total_discounts'], 2) }}
                </div>
                <p class="text-xs mt-1" style="color: rgba(180, 83, 9, 0.8);">
                    {{ __('Item + coupon discounts') }}
                </p>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md" style="background-color: #f5f3ff; border-color: #ddd6fe; color: #2e1065;">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">{{ __('Total Orders') }}</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #ede9fe; color: #6d28d9;">
                    <x-heroicon-o-receipt-percent class="h-4 w-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">
                    {{ number_format($kpis['total_orders']) }}
                </div>
                <p class="text-xs mt-1" style="color: rgba(109, 40, 217, 0.8);">
                    {{ __('Completed sales count') }}
                </p>
            </div>
        </div>

        <!-- Average Order Value -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md" style="background-color: #eef2ff; border-color: #c7d2fe; color: #1e1b4b;">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">{{ __('Average Order Value') }}</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #e0e7ff; color: #4338ca;">
                    <x-heroicon-o-chart-pie class="w-4 h-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">
                    ₹ {{ number_format($kpis['average_order_value'], 2) }}
                </div>
                <p class="text-xs mt-1" style="color: rgba(67, 56, 202, 0.8);">
                    {{ __('Net sales / total orders') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Sales by Payment Method Section -->
    <div class="bg-card border border-border rounded-xl p-5 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border pb-3">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-credit-card class="w-5 h-5 text-primary" />
                    <span>{{ __('Sales Breakdown by Payment Method') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('Net revenue, transaction volume, and share by UPI, Card, Cash, and Bank Transfer.') }}
                </p>
            </div>
        </div>

        <!-- Payment Method Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- UPI Card -->
            @php $upi = $paymentMethods['upi'] ?? ['amount' => 0, 'count' => 0, 'percentage' => 0]; @endphp
            <div class="rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-950/20 p-4 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-white font-bold text-xs">
                            UPI
                        </span>
                        <div>
                            <div class="font-semibold text-sm text-foreground">UPI / QR</div>
                            <div class="text-xs text-muted-foreground">{{ $upi['count'] }} {{ \Illuminate\Support\Str::plural('order', $upi['count']) }}</div>
                        </div>
                    </div>
                    <span class="rounded-full bg-blue-100 dark:bg-blue-900/60 px-2.5 py-0.5 text-xs font-bold text-blue-700 dark:text-blue-300">
                        {{ $upi['percentage'] }}%
                    </span>
                </div>
                <div class="mt-4">
                    <div class="text-xl font-bold text-foreground">
                        ₹ {{ number_format($upi['amount'], 2) }}
                    </div>
                    <div class="mt-2 h-1.5 w-full rounded-full bg-blue-200 dark:bg-blue-900">
                        <div class="h-1.5 rounded-full bg-blue-600" style="width: {{ min(100, $upi['percentage']) }}%"></div>
                    </div>
                </div>
            </div>

            <!-- Card -->
            @php $card = $paymentMethods['card'] ?? ['amount' => 0, 'count' => 0, 'percentage' => 0]; @endphp
            <div class="rounded-xl border border-purple-200 bg-purple-50/50 dark:border-purple-900 dark:bg-purple-950/20 p-4 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-600 text-white font-bold text-xs">
                            <x-heroicon-o-credit-card class="w-5 h-5" />
                        </span>
                        <div>
                            <div class="font-semibold text-sm text-foreground">Card / POS</div>
                            <div class="text-xs text-muted-foreground">{{ $card['count'] }} {{ \Illuminate\Support\Str::plural('order', $card['count']) }}</div>
                        </div>
                    </div>
                    <span class="rounded-full bg-purple-100 dark:bg-purple-900/60 px-2.5 py-0.5 text-xs font-bold text-purple-700 dark:text-purple-300">
                        {{ $card['percentage'] }}%
                    </span>
                </div>
                <div class="mt-4">
                    <div class="text-xl font-bold text-foreground">
                        ₹ {{ number_format($card['amount'], 2) }}
                    </div>
                    <div class="mt-2 h-1.5 w-full rounded-full bg-purple-200 dark:bg-purple-900">
                        <div class="h-1.5 rounded-full bg-purple-600" style="width: {{ min(100, $card['percentage']) }}%"></div>
                    </div>
                </div>
            </div>

            <!-- Cash Card -->
            @php $cash = $paymentMethods['cash'] ?? ['amount' => 0, 'count' => 0, 'percentage' => 0]; @endphp
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 dark:border-emerald-900 dark:bg-emerald-950/20 p-4 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white font-bold text-xs">
                            <x-heroicon-o-banknotes class="w-5 h-5" />
                        </span>
                        <div>
                            <div class="font-semibold text-sm text-foreground">Cash</div>
                            <div class="text-xs text-muted-foreground">{{ $cash['count'] }} {{ \Illuminate\Support\Str::plural('order', $cash['count']) }}</div>
                        </div>
                    </div>
                    <span class="rounded-full bg-emerald-100 dark:bg-emerald-900/60 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                        {{ $cash['percentage'] }}%
                    </span>
                </div>
                <div class="mt-4">
                    <div class="text-xl font-bold text-foreground">
                        ₹ {{ number_format($cash['amount'], 2) }}
                    </div>
                    <div class="mt-2 h-1.5 w-full rounded-full bg-emerald-200 dark:bg-emerald-900">
                        <div class="h-1.5 rounded-full bg-emerald-600" style="width: {{ min(100, $cash['percentage']) }}%"></div>
                    </div>
                </div>
            </div>

            <!-- Transfer Card -->
            @php $transfer = $paymentMethods['transfer'] ?? ['amount' => 0, 'count' => 0, 'percentage' => 0]; @endphp
            <div class="rounded-xl border border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20 p-4 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-600 text-white font-bold text-xs">
                            <x-heroicon-o-building-library class="w-5 h-5" />
                        </span>
                        <div>
                            <div class="font-semibold text-sm text-foreground">Bank Transfer</div>
                            <div class="text-xs text-muted-foreground">{{ $transfer['count'] }} {{ \Illuminate\Support\Str::plural('order', $transfer['count']) }}</div>
                        </div>
                    </div>
                    <span class="rounded-full bg-amber-100 dark:bg-amber-900/60 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:text-amber-300">
                        {{ $transfer['percentage'] }}%
                    </span>
                </div>
                <div class="mt-4">
                    <div class="text-xl font-bold text-foreground">
                        ₹ {{ number_format($transfer['amount'], 2) }}
                    </div>
                    <div class="mt-2 h-1.5 w-full rounded-full bg-amber-200 dark:bg-amber-900">
                        <div class="h-1.5 rounded-full bg-amber-600" style="width: {{ min(100, $transfer['percentage']) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Proportional Stacked Bar -->
        @if($kpis['net_sales'] > 0)
        <div class="pt-3">
            <div class="flex items-center justify-between text-xs text-muted-foreground mb-1.5">
                <span>{{ __('Revenue Share Distribution') }}</span>
                <span>{{ __('Total: ₹ ') }}{{ number_format($kpis['net_sales'], 2) }}</span>
            </div>
            <div class="flex h-3 w-full overflow-hidden rounded-full bg-muted">
                @if(($paymentMethods['upi']['percentage'] ?? 0) > 0)
                    <div style="width: {{ $paymentMethods['upi']['percentage'] }}%" class="bg-blue-600" title="UPI: {{ $paymentMethods['upi']['percentage'] }}%"></div>
                @endif
                @if(($paymentMethods['card']['percentage'] ?? 0) > 0)
                    <div style="width: {{ $paymentMethods['card']['percentage'] }}%" class="bg-purple-600" title="Card: {{ $paymentMethods['card']['percentage'] }}%"></div>
                @endif
                @if(($paymentMethods['cash']['percentage'] ?? 0) > 0)
                    <div style="width: {{ $paymentMethods['cash']['percentage'] }}%" class="bg-emerald-600" title="Cash: {{ $paymentMethods['cash']['percentage'] }}%"></div>
                @endif
                @if(($paymentMethods['transfer']['percentage'] ?? 0) > 0)
                    <div style="width: {{ $paymentMethods['transfer']['percentage'] }}%" class="bg-amber-600" title="Bank Transfer: {{ $paymentMethods['transfer']['percentage'] }}%"></div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- Daily Sales, Profit & Collections Summary Table -->
    <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-border flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-table-cells class="w-5 h-5 text-primary" />
                    <span>{{ __('Daily Sales, Profit & Collections Summary') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('Day-by-day revenue, profit & margin, invoice counts, and payment mode breakdowns.') }}
                </p>
            </div>
            <div class="text-xs text-muted-foreground">
                {{ $dailyBreakdown->count() }} {{ __('active sales days') }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-foreground">
                <thead class="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Orders') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Gross Sales') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Discounts') }}</th>
                        <th class="px-4 py-3 text-right font-bold text-foreground">{{ __('Total Sales') }}</th>
                        <th class="px-4 py-3 text-right text-emerald-700 dark:text-emerald-400 font-bold">{{ __('Gross Profit') }}</th>
                        <th class="px-4 py-3 text-right text-blue-600 dark:text-blue-400">{{ __('UPI') }}</th>
                        <th class="px-4 py-3 text-right text-purple-600 dark:text-purple-400">{{ __('Card') }}</th>
                        <th class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ __('Cash') }}</th>
                        <th class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">{{ __('Transfer') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($dailyBreakdown as $row)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 font-medium whitespace-nowrap">
                                {{ $row['formatted_date'] }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground">
                                    {{ $row['orders_count'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-muted-foreground">
                                ₹ {{ number_format($row['gross_sales'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-amber-600 dark:text-amber-400">
                                ₹ {{ number_format($row['total_discounts'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-bold text-foreground">
                                ₹ {{ number_format($row['net_sales'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-bold {{ $row['gross_profit'] >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                ₹ {{ number_format($row['gross_profit'], 2) }}
                                <span class="text-xs font-normal opacity-80">({{ $row['margin'] }}%)</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-blue-600 dark:text-blue-400">
                                ₹ {{ number_format($row['upi'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-purple-600 dark:text-purple-400">
                                ₹ {{ number_format($row['card'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-emerald-600 dark:text-emerald-400">
                                ₹ {{ number_format($row['cash'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-amber-600 dark:text-amber-400">
                                ₹ {{ number_format($row['transfer'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <x-heroicon-o-information-circle class="w-8 h-8 text-muted-foreground" />
                                    <span>{{ __('No completed sales found in this date range.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($dailyBreakdown->isNotEmpty())
                    <tfoot class="bg-muted/80 font-bold border-t-2 border-border text-foreground">
                        <tr>
                            <td class="px-4 py-3 uppercase text-xs">{{ __('Total') }}</td>
                            <td class="px-4 py-3 text-center">{{ $kpis['total_orders'] }}</td>
                            <td class="px-4 py-3 text-right">₹ {{ number_format($kpis['gross_sales'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">₹ {{ number_format($kpis['total_discounts'], 2) }}</td>
                            <td class="px-4 py-3 text-right">₹ {{ number_format($kpis['net_sales'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-bold {{ $kpis['gross_profit'] >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                ₹ {{ number_format($kpis['gross_profit'], 2) }}
                                <span class="text-xs font-normal opacity-80">({{ $kpis['margin'] }}%)</span>
                            </td>
                            <td class="px-4 py-3 text-right text-blue-600 dark:text-blue-400">₹ {{ number_format($paymentMethods['upi']['amount'] ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right text-purple-600 dark:text-purple-400">₹ {{ number_format($paymentMethods['card']['amount'] ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">₹ {{ number_format($paymentMethods['cash']['amount'] ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">₹ {{ number_format($paymentMethods['transfer']['amount'] ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- Completed Invoices in Period (Transparent, Reconciled Math: Gross - Disc = Net, Net - Cost = Profit) -->
    <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-border flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-receipt-percent class="w-5 h-5 text-primary" />
                    <span>{{ __('Completed Invoices in Period') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('Complete itemized transaction audit: Gross Amount, Total Discount, Net Total, Product Cost (COGS), and Gross Profit.') }}
                </p>
            </div>
            <span class="text-xs font-medium text-muted-foreground">
                {{ count($sales) }} {{ __('Invoices') }}
            </span>
        </div>

        <div class="overflow-x-auto max-h-[30rem]">
            <table class="w-full text-left text-sm text-foreground">
                <thead class="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground sticky top-0 border-b border-border z-10">
                    <tr>
                        <th class="px-4 py-3">{{ __('Invoice #') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3">{{ __('Items Type') }}</th>
                        <th class="px-4 py-3">{{ __('Payment Mode') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Gross') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Discount') }}</th>
                        <th class="px-4 py-3 text-right font-bold text-foreground">{{ __('Net Total') }}</th>
                        <th class="px-4 py-3 text-right text-muted-foreground">{{ __('Cost (COGS)') }}</th>
                        <th class="px-4 py-3 text-right font-bold">{{ __('Gross Profit') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($sales as $sale)
                        @php
                            $methodVal = $sale->payment_method instanceof \App\Enums\PaymentMethod
                                ? $sale->payment_method->value
                                : (string) $sale->payment_method;
                            $methodLabel = $sale->payment_method instanceof \App\Enums\PaymentMethod
                                ? $sale->payment_method->label()
                                : ucfirst((string) $sale->payment_method);
                            $badgeClass = match($methodVal) {
                                'upi' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300',
                                'card' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300',
                                'cash' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300',
                                'transfer' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300',
                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
                            };

                            $productCount = $sale->items->filter(fn($i) => !empty($i->product_id))->count();
                            $serviceCount = $sale->items->filter(fn($i) => !empty($i->service_id))->count();
                            $profit = (float) ($sale->gross_profit ?? 0);
                            $margin = (float) ($sale->margin ?? 0);
                            $cogs = (float) ($sale->cogs ?? 0);
                            $discount = (float) ($sale->total_discount ?? 0);
                        @endphp
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 font-semibold text-primary whitespace-nowrap">
                                <a href="{{ route('sales.show', $sale) }}" class="hover:underline flex items-center gap-1">
                                    {{ $sale->invoice_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-muted-foreground text-xs">
                                {{ $sale->sale_date ? $sale->sale_date->format('d M Y, h:i A') : '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-medium text-foreground">{{ $sale->customer?->name ?? 'Walk-in Customer' }}</div>
                                @if($sale->customer?->phone)
                                    <div class="text-xs text-muted-foreground">{{ $sale->customer->phone }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-muted-foreground">
                                @if($productCount > 0 && $serviceCount > 0)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-medium text-[11px]">
                                        {{ $productCount }}p + {{ $serviceCount }}s
                                    </span>
                                @elseif($serviceCount > 0)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 font-medium text-[11px]" title="Services have 0 merchandise inventory cost">
                                        {{ $serviceCount }} {{ \Illuminate\Support\Str::plural('service', $serviceCount) }}
                                    </span>
                                @elseif($productCount > 0)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-muted text-foreground font-medium text-[11px]">
                                        {{ $productCount }} {{ \Illuminate\Support\Str::plural('product', $productCount) }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $badgeClass }}">
                                    {{ $methodLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-muted-foreground">
                                ₹ {{ number_format((float) $sale->subtotal, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-amber-600 dark:text-amber-400">
                                @if($discount > 0)
                                    -₹ {{ number_format($discount, 2) }}
                                @else
                                    <span class="text-muted-foreground opacity-60">₹ 0.00</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-bold text-foreground">
                                ₹ {{ number_format((float) $sale->total, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-muted-foreground text-xs">
                                ₹ {{ number_format($cogs, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-bold {{ $profit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                <div>₹ {{ number_format($profit, 2) }}</div>
                                <div class="text-[10px] font-normal opacity-75">
                                    {{ number_format($margin, 1) }}% margin
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center text-xs text-primary hover:underline font-medium">
                                    <x-heroicon-o-eye class="w-4 h-4 mr-1" />
                                    {{ __('View') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-6 text-center text-muted-foreground text-sm">
                                {{ __('No completed sales in this range.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
