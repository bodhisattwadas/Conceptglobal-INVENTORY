<div class="space-y-6">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <!-- Filter and Action Bar (Matched to Dashboard Overview Header) -->
    <div class="bg-card border border-border rounded-xl p-4 sm:p-5 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="shrink-0">
                <h2 class="text-lg font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-chart-bar class="w-5 h-5 text-primary" />
                    <span>{{ __('Overview') }}</span>
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ __('Monitor your sales performance, profit margins, and collections at a glance.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-start lg:justify-end gap-2">
                <!-- Inline Fetching Loader Pill -->
                <div
                    wire:loading.flex
                    wire:target="dateFilter, startDate, endDate"
                    class="items-center gap-1.5 h-9 px-3 rounded-md bg-primary/10 text-primary text-xs font-semibold border border-primary/20 shadow-xs"
                >
                    <svg class="w-4 h-4 animate-spin text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __('Fetching data...') }}</span>
                </div>

                <!-- Period Selector -->
                <div class="relative inline-flex items-center">
                    <select
                        id="dateFilter"
                        wire:model.live="dateFilter"
                        wire:loading.attr="disabled"
                        wire:target="dateFilter, startDate, endDate"
                        class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm font-medium text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-60 cursor-pointer"
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
                </div>

                <!-- Custom Range Inputs (Only shown when Custom Range is selected) -->
                <div x-show="$wire.dateFilter === 'custom'" x-cloak x-transition class="flex items-center gap-2">
                    <input
                        type="date"
                        wire:model.live="startDate"
                        wire:loading.attr="disabled"
                        wire:target="dateFilter, startDate, endDate"
                        class="h-9 rounded-md border border-input bg-background px-2.5 py-1 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-60"
                    />
                    <span class="text-xs text-muted-foreground">{{ __('to') }}</span>
                    <input
                        type="date"
                        wire:model.live="endDate"
                        wire:loading.attr="disabled"
                        wire:target="dateFilter, startDate, endDate"
                        class="h-9 rounded-md border border-input bg-background px-2.5 py-1 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-60"
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
                    href="{{ route('reports.sales.xls', ['start_date' => $startDate, 'end_date' => $endDate, 'section' => 'full']) }}"
                    class="print:hidden inline-flex items-center gap-1.5 rounded-md border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 h-9 px-3 text-sm font-medium shadow-sm transition-colors"
                    title="{{ __('Download Complete Multi-Sheet Excel Workbook (XLSX)') }}"
                >
                    <x-heroicon-o-table-cells class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    <span>{{ __('XLS') }}</span>
                </a>

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
            <span
                wire:loading.inline-flex
                wire:target="dateFilter, startDate, endDate"
                class="items-center gap-1.5 ml-2 text-primary font-semibold"
            >
                <svg class="w-3.5 h-3.5 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ __('Loading report metrics...') }}</span>
            </span>
            <span class="ml-auto font-medium text-foreground">
                {{ $kpis['total_orders'] }} {{ \Illuminate\Support\Str::plural('transaction', $kpis['total_orders']) }}
            </span>
        </div>
    </div>

    <!-- Stats Grid (Executive P&L & Cash Flow KPIs) -->
    <div
        wire:loading.class="opacity-50 pointer-events-none transition-opacity duration-150"
        wire:target="dateFilter, startDate, endDate"
        class="grid gap-3 sm:gap-4 grid-cols-2 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8"
    >
        <!-- 1. Gross Sales -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md p-3.5 bg-teal-50/70 border-teal-200 text-teal-950 dark:bg-teal-950/20 dark:border-teal-900/60 dark:text-teal-200">
            <div class="flex items-center justify-between pb-1">
                <h3 class="tracking-tight text-xs font-bold uppercase">{{ __('Gross Sales') }}</h3>
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-900/60 dark:text-teal-300">
                    <x-heroicon-o-shopping-bag class="h-3.5 w-3.5" />
                </span>
            </div>
            <div class="text-base sm:text-lg font-bold mt-1">
                ₹ {{ number_format($kpis['gross_sales'], 2) }}
            </div>
            <p class="text-[11px] text-teal-700/80 dark:text-teal-400 mt-0.5 truncate" title="{{ __('Pre-discount retail total') }}">
                {{ __('Pre-discount total') }}
            </p>
        </div>

        <!-- Discounts -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md p-3.5 bg-amber-50/70 border-amber-200 text-amber-950 dark:bg-amber-950/20 dark:border-amber-900/60 dark:text-amber-200">
            <div class="flex items-center justify-between pb-1">
                <h3 class="tracking-tight text-xs font-bold uppercase">{{ __('Discounts') }}</h3>
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300">
                    <x-heroicon-o-tag class="h-3.5 w-3.5" />
                </span>
            </div>
            <div class="text-base sm:text-lg font-bold mt-1 text-amber-600 dark:text-amber-400">
                -₹ {{ number_format($kpis['total_discounts'], 2) }}
            </div>
            <p class="text-[11px] text-amber-700/80 dark:text-amber-400 mt-0.5 truncate" title="{{ __('Item + coupon discounts') }}">
                {{ __('Discounts given') }}
            </p>
        </div>

        <!-- 9. Total Cash Inflow (Total Sales) -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md p-3.5 bg-blue-50/70 border-blue-200 text-blue-950 dark:bg-blue-950/20 dark:border-blue-900/60 dark:text-blue-200">
            <div class="flex items-center justify-between pb-1">
                <h3 class="tracking-tight text-xs font-bold uppercase">{{ __('Cash Inflow') }}</h3>
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300">
                    <x-heroicon-o-banknotes class="h-3.5 w-3.5" />
                </span>
            </div>
            <div class="text-base sm:text-lg font-bold mt-1 text-blue-700 dark:text-blue-400">
                ₹ {{ number_format($kpis['net_sales'], 2) }}
            </div>
            <p class="text-[11px] text-blue-700/80 dark:text-blue-400 mt-0.5 truncate" title="{{ __('Total Sales (Net Cash Received)') }}">
                {{ __('Total Sales') }}
            </p>
        </div>

        <!-- 2. COGS (Cost of Goods Sold) -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md p-3.5 bg-rose-50/70 border-rose-200 text-rose-950 dark:bg-rose-950/20 dark:border-rose-900/60 dark:text-rose-200">
            <div class="flex items-center justify-between pb-1">
                <h3 class="tracking-tight text-xs font-bold uppercase">{{ __('COGS') }}</h3>
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300">
                    <x-heroicon-o-cube class="h-3.5 w-3.5" />
                </span>
            </div>
            <div class="text-base sm:text-lg font-bold mt-1 text-rose-600 dark:text-rose-400">
                ₹ {{ number_format($kpis['cogs'], 2) }}
            </div>
            <p class="text-[11px] text-rose-700/80 dark:text-rose-400 mt-0.5 truncate" title="{{ __('FIFO purchase cost of goods sold') }}">
                {{ __('Cost of Goods Sold') }}
            </p>
        </div>

        <!-- 3. Gross Profit (Gross Sale - COGS) -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md p-3.5 bg-emerald-50/70 border-emerald-200 text-emerald-950 dark:bg-emerald-950/20 dark:border-emerald-900/60 dark:text-emerald-200">
            <div class="flex items-center justify-between pb-1">
                <h3 class="tracking-tight text-xs font-bold uppercase">{{ __('Gross Profit') }}</h3>
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                    <x-heroicon-o-arrow-trending-up class="h-3.5 w-3.5" />
                </span>
            </div>
            <div class="text-base sm:text-lg font-bold mt-1 {{ $kpis['gross_profit'] >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600' }}">
                ₹ {{ number_format($kpis['gross_profit'], 2) }}
            </div>
            <p class="text-[11px] text-emerald-700/90 dark:text-emerald-400 mt-0.5 font-semibold truncate" title="{{ __('Net Sales minus COGS') }}">
                {{ number_format((float) $kpis['margin'], 1) }}% {{ __('margin') }}
            </p>
        </div>

        <!-- 4. Other Expenses (Manual) -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md p-3.5 bg-orange-50/70 border-orange-200 text-orange-950 dark:bg-orange-950/20 dark:border-orange-900/60 dark:text-orange-200">
            <div class="flex items-center justify-between pb-1">
                <h3 class="tracking-tight text-xs font-bold uppercase">{{ __('Other Expenses') }}</h3>
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-orange-100 text-orange-700 dark:bg-orange-900/60 dark:text-orange-300">
                    <x-heroicon-o-receipt-percent class="h-3.5 w-3.5" />
                </span>
            </div>
            <div class="text-base sm:text-lg font-bold mt-1 text-orange-600 dark:text-orange-400">
                ₹ {{ number_format($kpis['other_expenses'] ?? 0, 2) }}
            </div>
            <p class="text-[11px] text-orange-700/80 dark:text-orange-400 mt-0.5 truncate" title="{{ __('Manual system expenses') }}">
                {{ __('Outflow entries') }}
            </p>
        </div>

        <!-- 5. Net Profit (Gross Profit - Other Expenses) -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md p-3.5 {{ ($kpis['net_profit'] ?? 0) >= 0 ? 'bg-indigo-50/70 border-indigo-200 text-indigo-950 dark:bg-indigo-950/20 dark:border-indigo-900/60 dark:text-indigo-200' : 'bg-red-50/70 border-red-200 text-red-950 dark:bg-red-950/20 dark:border-red-900/60 dark:text-red-200' }}">
            <div class="flex items-center justify-between pb-1">
                <h3 class="tracking-tight text-xs font-bold uppercase">{{ __('Net Profit') }}</h3>
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg {{ ($kpis['net_profit'] ?? 0) >= 0 ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300' }}">
                    <x-heroicon-o-chart-bar class="h-3.5 w-3.5" />
                </span>
            </div>
            <div class="text-base sm:text-lg font-bold mt-1 {{ ($kpis['net_profit'] ?? 0) >= 0 ? 'text-indigo-700 dark:text-indigo-400' : 'text-red-600 dark:text-red-400' }}">
                ₹ {{ number_format($kpis['net_profit'] ?? 0, 2) }}
            </div>
            <p class="text-[11px] font-semibold mt-0.5 truncate {{ ($kpis['net_profit'] ?? 0) >= 0 ? 'text-indigo-700/90 dark:text-indigo-400' : 'text-red-600 dark:text-red-400' }}" title="{{ __('Gross Profit minus Other Expenses') }}">
                {{ number_format((float) ($kpis['net_profit_margin'] ?? 0), 1) }}% {{ __('net') }}
            </p>
        </div>

        <!-- Orders & AOV -->
        <div class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md p-3.5 bg-purple-50/70 border-purple-200 text-purple-950 dark:bg-purple-950/20 dark:border-purple-900/60 dark:text-purple-200">
            <div class="flex items-center justify-between pb-1">
                <h3 class="tracking-tight text-xs font-bold uppercase">{{ __('Orders & AOV') }}</h3>
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300">
                    <x-heroicon-o-chart-pie class="h-3.5 w-3.5" />
                </span>
            </div>
            <div class="text-base sm:text-lg font-bold mt-1 text-purple-700 dark:text-purple-400">
                {{ number_format($kpis['total_orders']) }} <span class="text-xs font-normal text-muted-foreground">orders</span>
            </div>
            <p class="text-[11px] text-purple-700/80 dark:text-purple-400 mt-0.5 truncate">
                Avg: ₹ {{ number_format($kpis['average_order_value'], 2) }}
            </p>
        </div>
    </div>

    <!-- Executive Cash & Performance Summary Cards (Highlights) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- 6. Top 5 Brand with Sales -->
        <div class="bg-card border border-border rounded-xl p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
                    <x-heroicon-o-building-storefront class="w-4 h-4 text-primary" />
                    <span>{{ __('Top 5 Brands by Sales') }}</span>
                </h3>
                <span class="text-[11px] text-muted-foreground font-medium">{{ __('Revenue & Share') }}</span>
            </div>
            <div class="divide-y divide-border/60">
                @forelse($topBrands as $idx => $brand)
                    <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-[11px] shrink-0">
                                #{{ $idx + 1 }}
                            </span>
                            <div class="min-w-0">
                                <div class="font-semibold text-foreground truncate" title="{{ $brand['name'] }}">{{ $brand['name'] }}</div>
                                <div class="text-[11px] text-muted-foreground">{{ number_format($brand['total_quantity']) }} {{ __('units sold') }}</div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold text-foreground">₹ {{ number_format($brand['net_sales'], 2) }}</div>
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">{{ $brand['percentage'] }}% {{ __('share') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-muted-foreground">{{ __('No brand sales recorded.') }}</div>
                @endforelse
            </div>
        </div>

        <!-- 7. Top 5 Customers with Sales & Transactions Count -->
        <div class="bg-card border border-border rounded-xl p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
                    <x-heroicon-o-user-group class="w-4 h-4 text-primary" />
                    <span>{{ __('Top 5 Customers with Sales') }}</span>
                </h3>
                <span class="text-[11px] text-muted-foreground font-medium">{{ __('Sales & Txns') }}</span>
            </div>
            <div class="divide-y divide-border/60">
                @forelse($topCustomers as $idx => $cust)
                    <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300 font-bold text-[11px] shrink-0">
                                #{{ $idx + 1 }}
                            </span>
                            <div class="min-w-0">
                                <div class="font-semibold text-foreground truncate" title="{{ $cust['name'] }}">{{ $cust['name'] }}</div>
                                <div class="text-[11px] text-muted-foreground font-mono truncate">{{ $cust['phone'] }}</div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold text-foreground">₹ {{ number_format($cust['sales_amount'], 2) }}</div>
                            <div class="text-[11px] text-muted-foreground font-medium">{{ $cust['transactions_count'] }} {{ \Illuminate\Support\Str::plural('tx', $cust['transactions_count']) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-muted-foreground">{{ __('No customer records found.') }}</div>
                @endforelse
            </div>
        </div>

        <!-- 10. Total Outflow by Expense Category -->
        <div class="bg-card border border-border rounded-xl p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
                    <x-heroicon-o-receipt-percent class="w-4 h-4 text-orange-600 dark:text-orange-400" />
                    <span>{{ __('Total Outflow by Expense Category') }}</span>
                </h3>
                <div class="text-right">
                    <span class="text-xs font-bold text-orange-600 dark:text-orange-400">₹ {{ number_format($expenses['total'] ?? 0, 2) }}</span>
                </div>
            </div>
            <div class="divide-y divide-border/60">
                @forelse($expenses['by_category'] ?? [] as $exp)
                    <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="font-semibold text-foreground truncate">{{ $exp['category_name'] }}</span>
                                <span class="text-[11px] text-muted-foreground">{{ $exp['count'] }} {{ \Illuminate\Support\Str::plural('entry', $exp['count']) }}</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                                <div class="h-1.5 rounded-full bg-orange-500" style="width: {{ min(100, $exp['percentage']) }}%"></div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold text-orange-600 dark:text-orange-400">₹ {{ number_format($exp['total_amount'], 2) }}</div>
                            <div class="text-[11px] text-muted-foreground">{{ $exp['percentage'] }}%</div>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-muted-foreground">
                        {{ __('No manual expense outflow entries in this period.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 8. Top 10 Products by Sales -->
    <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-border flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-cube class="w-5 h-5 text-primary" />
                    <span>{{ __('Top 10 Products by Sales') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('Top performing merchandise ranked by net revenue, sold volume, purchase cost, and gross profit.') }}
                </p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-muted text-muted-foreground uppercase text-[11px] font-semibold border-b border-border">
                    <tr>
                        <th class="py-3 px-4 text-center w-12">{{ __('Rank') }}</th>
                        <th class="py-3 px-4">{{ __('Product Name') }}</th>
                        <th class="py-3 px-4">{{ __('Code / SKU') }}</th>
                        <th class="py-3 px-4">{{ __('Brand') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Qty Sold') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('Gross Sales') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('Discounts') }}</th>
                        <th class="py-3 px-4 text-right font-bold">{{ __('Net Sales') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('COGS') }}</th>
                        <th class="py-3 px-4 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ __('Gross Profit') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Margin') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($topProducts as $idx => $prod)
                        <tr class="hover:bg-accent/40 transition-colors">
                            <td class="py-2.5 px-4 text-center">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-[10px]">
                                    #{{ $idx + 1 }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 font-semibold text-foreground">{{ $prod['name'] }}</td>
                            <td class="py-2.5 px-4 font-mono text-[11px] text-muted-foreground">{{ $prod['code'] }}</td>
                            <td class="py-2.5 px-4">
                                <span class="rounded bg-muted px-2 py-0.5 text-[11px] font-medium text-foreground">
                                    {{ $prod['brand'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-center font-bold text-foreground">{{ number_format($prod['quantity']) }}</td>
                            <td class="py-2.5 px-4 text-right text-muted-foreground">₹ {{ number_format($prod['gross_sales'], 2) }}</td>
                            <td class="py-2.5 px-4 text-right text-amber-600 dark:text-amber-400">
                                {{ $prod['total_discounts'] > 0 ? '-₹ ' . number_format($prod['total_discounts'], 2) : '₹ 0.00' }}
                            </td>
                            <td class="py-2.5 px-4 text-right font-bold text-foreground">₹ {{ number_format($prod['net_sales'], 2) }}</td>
                            <td class="py-2.5 px-4 text-right text-muted-foreground">₹ {{ number_format($prod['cogs'], 2) }}</td>
                            <td class="py-2.5 px-4 text-right font-bold text-emerald-600 dark:text-emerald-400">₹ {{ number_format($prod['gross_profit'], 2) }}</td>
                            <td class="py-2.5 px-4 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ $prod['margin'] }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-6 text-center text-xs text-muted-foreground">
                                {{ __('No product sales recorded in this period.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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

    <!-- Reporting Sections with Tabs Navigation -->
    <div
        x-data="{
            activeTab: (typeof localStorage !== 'undefined' && localStorage.getItem('sales_report_active_tab')) || 'invoices',
            setTab(tab) {
                this.activeTab = tab;
                try {
                    localStorage.setItem('sales_report_active_tab', tab);
                } catch(e) {}
                this.$nextTick(() => {
                    window.dispatchEvent(new Event('resize'));
                    setTimeout(() => window.dispatchEvent(new Event('resize')), 60);
                    setTimeout(() => window.dispatchEvent(new Event('resize')), 200);
                });
            }
        }"
        class="space-y-4 pt-1"
    >
        <!-- Navigation Tab Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border pb-2">
            <div class="flex items-center gap-1.5 p-1 bg-muted/70 dark:bg-muted/30 rounded-xl border border-border/80 overflow-x-auto max-w-full">
                <!-- Tab: Completed Invoices (Blue) -->
                <button
                    type="button"
                    @click="setTab('invoices')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-all whitespace-nowrap cursor-pointer border"
                    :class="activeTab === 'invoices' 
                        ? 'bg-blue-50/90 dark:bg-blue-950/60 border-blue-200 dark:border-blue-800 text-blue-900 dark:text-blue-200 shadow-xs ring-1 ring-blue-500/20 font-bold' 
                        : 'border-transparent text-muted-foreground hover:text-blue-700 dark:hover:text-blue-300 hover:bg-blue-50/50 dark:hover:bg-blue-950/30'"
                >
                    <x-heroicon-o-receipt-percent class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" />
                    <span>{{ __('Completed Invoices') }}</span>
                    <span 
                        class="px-1.5 py-0.5 rounded-full text-[11px] font-mono transition-colors" 
                        :class="activeTab === 'invoices' ? 'bg-blue-200/80 dark:bg-blue-900/80 text-blue-800 dark:text-blue-200 font-bold' : 'bg-muted text-muted-foreground'"
                    >
                        {{ $sales->total() }}
                    </span>
                </button>

                <!-- Tab: Sales by Brand (Purple) -->
                <button
                    type="button"
                    @click="setTab('brands')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-all whitespace-nowrap cursor-pointer border"
                    :class="activeTab === 'brands' 
                        ? 'bg-purple-50/90 dark:bg-purple-950/60 border-purple-200 dark:border-purple-800 text-purple-900 dark:text-purple-200 shadow-xs ring-1 ring-purple-500/20 font-bold' 
                        : 'border-transparent text-muted-foreground hover:text-purple-700 dark:hover:text-purple-300 hover:bg-purple-50/50 dark:hover:bg-purple-950/30'"
                >
                    <x-heroicon-o-tag class="w-4 h-4 text-purple-600 dark:text-purple-400 shrink-0" />
                    <span>{{ __('Sales by Brand') }}</span>
                    <span 
                        class="px-1.5 py-0.5 rounded-full text-[11px] font-mono transition-colors" 
                        :class="activeTab === 'brands' ? 'bg-purple-200/80 dark:bg-purple-900/80 text-purple-800 dark:text-purple-200 font-bold' : 'bg-muted text-muted-foreground'"
                    >
                        {{ $brandBreakdown->total() }}
                    </span>
                </button>

                <!-- Tab: Sales by Item (Emerald) -->
                <button
                    type="button"
                    @click="setTab('items')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-all whitespace-nowrap cursor-pointer border"
                    :class="activeTab === 'items' 
                        ? 'bg-emerald-50/90 dark:bg-emerald-950/60 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 shadow-xs ring-1 ring-emerald-500/20 font-bold' 
                        : 'border-transparent text-muted-foreground hover:text-emerald-700 dark:hover:text-emerald-300 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/30'"
                >
                    <x-heroicon-o-cube class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span>{{ __('Sales by Item') }}</span>
                    <span 
                        class="px-1.5 py-0.5 rounded-full text-[11px] font-mono transition-colors" 
                        :class="activeTab === 'items' ? 'bg-emerald-200/80 dark:bg-emerald-900/80 text-emerald-800 dark:text-emerald-200 font-bold' : 'bg-muted text-muted-foreground'"
                    >
                        {{ $itemBreakdown->total() }}
                    </span>
                </button>

                <!-- Tab: Daily Breakdown (Amber) -->
                <button
                    type="button"
                    @click="setTab('daily')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-all whitespace-nowrap cursor-pointer border"
                    :class="activeTab === 'daily' 
                        ? 'bg-amber-50/90 dark:bg-amber-950/60 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 shadow-xs ring-1 ring-amber-500/20 font-bold' 
                        : 'border-transparent text-muted-foreground hover:text-amber-700 dark:hover:text-amber-300 hover:bg-amber-50/50 dark:hover:bg-amber-950/30'"
                >
                    <x-heroicon-o-calendar-days class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
                    <span>{{ __('Daily Summary') }}</span>
                    <span 
                        class="px-1.5 py-0.5 rounded-full text-[11px] font-mono transition-colors" 
                        :class="activeTab === 'daily' ? 'bg-amber-200/80 dark:bg-amber-900/80 text-amber-800 dark:text-amber-200 font-bold' : 'bg-muted text-muted-foreground'"
                    >
                        {{ $dailyBreakdown->total() }}
                    </span>
                </button>

                <!-- Tab: All Views (Indigo) -->
                <button
                    type="button"
                    @click="setTab('all')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-all whitespace-nowrap cursor-pointer border"
                    :class="activeTab === 'all' 
                        ? 'bg-indigo-50/90 dark:bg-indigo-950/60 border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200 shadow-xs ring-1 ring-indigo-500/20 font-bold' 
                        : 'border-transparent text-muted-foreground hover:text-indigo-700 dark:hover:text-indigo-300 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/30'"
                >
                    <x-heroicon-o-squares-2x2 class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                    <span>{{ __('All Views') }}</span>
                </button>
            </div>

            <div class="text-xs text-muted-foreground hidden sm:flex items-center gap-2">
                <span>{{ __('Active Tab:') }}</span>
                <span 
                    class="font-bold px-2 py-0.5 rounded-md text-xs transition-colors" 
                    :class="{
                        'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300': activeTab === 'invoices',
                        'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300': activeTab === 'brands',
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300': activeTab === 'items',
                        'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300': activeTab === 'daily',
                        'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300': activeTab === 'all',
                    }"
                    x-text="activeTab === 'all' ? '{{ __('All Views (Continuous)') }}' : (activeTab === 'brands' ? '{{ __('Sales by Brand') }}' : (activeTab === 'items' ? '{{ __('Sales by Item') }}' : (activeTab === 'daily' ? '{{ __('Daily Sales & Collections') }}' : '{{ __('Completed Invoices') }}')))"
                ></span>
            </div>
        </div>

        <!-- Daily Sales, Profit & Collections Summary Table -->
        <div
            x-show="activeTab === 'daily' || activeTab === 'all'"
            x-cloak
            wire:loading.class="opacity-60 transition-opacity duration-150"
            wire:target="dateFilter, startDate, endDate, dailySearch, dailyPerPage, sortByDaily"
            class="bg-card border border-border rounded-xl shadow-sm overflow-hidden"
        >
        <div class="p-5 border-b border-border flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-table-cells class="w-5 h-5 text-primary" />
                    <span>{{ __('Daily Sales, Profit & Collections Summary') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('Day-by-day revenue, profit & margin, invoice counts, and payment mode breakdowns.') }}
                </p>
            </div>
            <!-- Server-Side Controls & Exports -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div
                    wire:loading.flex
                    wire:target="dailySearch, dailyPerPage, sortByDaily"
                    class="items-center gap-1.5 px-2.5 py-1 rounded-lg bg-primary/10 text-primary text-xs font-semibold border border-primary/20"
                >
                    <svg class="w-3.5 h-3.5 animate-spin text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __('Fetching...') }}</span>
                </div>

                <!-- Search -->
                <div class="relative min-w-[180px] sm:min-w-[210px]">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="dailySearch"
                        placeholder="{{ __('Filter dates...') }}"
                        class="w-full pl-9 pr-7 py-1.5 text-xs rounded-lg border border-border bg-background focus:ring-1 focus:ring-primary focus:outline-none placeholder:text-muted-foreground"
                    />
                    @if($dailySearch)
                        <button type="button" wire:click="$set('dailySearch', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                            <x-heroicon-s-x-mark class="w-3.5 h-3.5" />
                        </button>
                    @endif
                </div>

                <!-- Per Page -->
                <select
                    wire:model.live="dailyPerPage"
                    class="text-xs py-1.5 px-2.5 rounded-lg border border-border bg-background text-foreground focus:ring-1 focus:ring-primary focus:outline-none"
                >
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="all">{{ __('All') }}</option>
                </select>

                <!-- Export XLS -->
                <a
                    href="{{ route('reports.sales.xls', ['start_date' => $startDate, 'end_date' => $endDate, 'section' => 'daily']) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800 transition-colors"
                    title="{{ __('Download Daily Breakdown Excel Spreadsheet') }}"
                >
                    <x-heroicon-o-table-cells class="w-3.5 h-3.5" />
                    <span>{{ __('XLS') }}</span>
                </a>

                <!-- Export PDF -->
                <a
                    href="{{ route('reports.sales.pdf', ['start_date' => $startDate, 'end_date' => $endDate, 'section' => 'daily']) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/70 border border-red-200 dark:border-red-800 transition-colors"
                    title="{{ __('Download Daily Breakdown PDF') }}"
                >
                    <x-heroicon-o-document-arrow-down class="w-3.5 h-3.5" />
                    <span>{{ __('PDF') }}</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-foreground">
                <thead class="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('date')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Date') }}</span>
                                @if($dailySortField === 'date')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('orders_count')">
                            <div class="flex items-center justify-center gap-1">
                                <span>{{ __('Orders') }}</span>
                                @if($dailySortField === 'orders_count')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('gross_sales')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Gross Sales') }}</span>
                                @if($dailySortField === 'gross_sales')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('total_discounts')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Discounts') }}</span>
                                @if($dailySortField === 'total_discounts')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right font-bold text-foreground cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('net_sales')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Total Sales') }}</span>
                                @if($dailySortField === 'net_sales')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right text-emerald-700 dark:text-emerald-400 font-bold cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('gross_profit')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Gross Profit') }}</span>
                                @if($dailySortField === 'gross_profit')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right text-blue-600 dark:text-blue-400 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('upi')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('UPI') }}</span>
                                @if($dailySortField === 'upi')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right text-purple-600 dark:text-purple-400 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('card')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Card') }}</span>
                                @if($dailySortField === 'card')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('cash')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Cash') }}</span>
                                @if($dailySortField === 'cash')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right text-amber-600 dark:text-amber-400 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByDaily('transfer')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Transfer') }}</span>
                                @if($dailySortField === 'transfer')
                                    <span class="text-primary">{{ $dailySortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
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
                                    <span>{{ __('No completed sales found matching filters.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($allDaily->isNotEmpty())
                    <tfoot class="bg-muted/80 font-bold border-t-2 border-border text-foreground">
                        <tr>
                            <td class="px-4 py-3 uppercase text-xs">{{ __('Period Total') }}</td>
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

        <!-- Daily Pagination Footer -->
        <div class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground bg-muted/20">
            <div>
                {{ __('Showing') }} <span class="font-semibold text-foreground">{{ $dailyBreakdown->firstItem() ?? 0 }}</span>
                {{ __('to') }} <span class="font-semibold text-foreground">{{ $dailyBreakdown->lastItem() ?? 0 }}</span>
                {{ __('of') }} <span class="font-semibold text-foreground">{{ $dailyBreakdown->total() }}</span> {{ __('days') }}
                @if($dailySearch)
                    <span class="text-primary font-medium">({{ __('filtered from') }} {{ $allDaily->count() }})</span>
                @endif
            </div>

            @if($dailyBreakdown->hasPages())
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        wire:click="previousPage('dailyPage')"
                        @disabled($dailyBreakdown->onFirstPage())
                        class="px-2.5 py-1 rounded border border-border bg-card text-foreground disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted text-xs font-medium cursor-pointer"
                    >
                        {{ __('Previous') }}
                    </button>
                    <span class="px-2 font-mono text-xs">
                        {{ $dailyBreakdown->currentPage() }} / {{ $dailyBreakdown->lastPage() }}
                    </span>
                    <button
                        type="button"
                        wire:click="nextPage('dailyPage')"
                        @disabled(!$dailyBreakdown->hasMorePages())
                        class="px-2.5 py-1 rounded border border-border bg-card text-foreground disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted text-xs font-medium cursor-pointer"
                    >
                        {{ __('Next') }}
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Sales by Brand Container -->
    <div
        x-show="activeTab === 'brands' || activeTab === 'all'"
        x-cloak
        wire:loading.class="opacity-60 transition-opacity duration-150"
        wire:target="dateFilter, startDate, endDate, brandSearch, brandPerPage, sortByBrand"
        class="bg-card border border-border rounded-xl shadow-sm overflow-hidden"
    >
        <!-- Header & Controls -->
        <div class="p-5 border-b border-border flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-tag class="w-5 h-5 text-primary" />
                    <span>{{ __('Sales by Brand') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('Revenue, volume, PO net purchase cost, and gross profit margins aggregated by brand.') }}
                </p>
            </div>
            <!-- Server-Side Controls & Exports -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div
                    wire:loading.flex
                    wire:target="brandSearch, brandPerPage, sortByBrand"
                    class="items-center gap-1.5 px-2.5 py-1 rounded-lg bg-primary/10 text-primary text-xs font-semibold border border-primary/20"
                >
                    <svg class="w-3.5 h-3.5 animate-spin text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __('Fetching...') }}</span>
                </div>

                <!-- Search -->
                <div class="relative min-w-[180px] sm:min-w-[210px]">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="brandSearch"
                        placeholder="{{ __('Search brand...') }}"
                        class="w-full pl-9 pr-7 py-1.5 text-xs rounded-lg border border-border bg-background focus:ring-1 focus:ring-primary focus:outline-none placeholder:text-muted-foreground"
                    />
                    @if($brandSearch)
                        <button type="button" wire:click="$set('brandSearch', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                            <x-heroicon-s-x-mark class="w-3.5 h-3.5" />
                        </button>
                    @endif
                </div>

                <!-- Per Page -->
                <select
                    wire:model.live="brandPerPage"
                    class="text-xs py-1.5 px-2.5 rounded-lg border border-border bg-background text-foreground focus:ring-1 focus:ring-primary focus:outline-none cursor-pointer"
                >
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="all">{{ __('All') }}</option>
                </select>

                <!-- Export XLS -->
                <a
                    href="{{ route('reports.sales.xls', ['start_date' => $startDate, 'end_date' => $endDate, 'section' => 'brands']) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800 transition-colors"
                    title="{{ __('Download Brands Excel Spreadsheet') }}"
                >
                    <x-heroicon-o-table-cells class="w-3.5 h-3.5" />
                    <span>{{ __('XLS') }}</span>
                </a>

                <!-- Export PDF -->
                <a
                    href="{{ route('reports.sales.pdf', ['start_date' => $startDate, 'end_date' => $endDate, 'section' => 'brands']) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/70 border border-red-200 dark:border-red-800 transition-colors"
                    title="{{ __('Download Brands PDF') }}"
                >
                    <x-heroicon-o-document-arrow-down class="w-3.5 h-3.5" />
                    <span>{{ __('PDF') }}</span>
                </a>
            </div>
        </div>

        <!-- Brand Visual Charts -->
        @if(!empty($brandChartData['categories']))
            <div
                class="p-5 border-b border-border bg-muted/10 grid grid-cols-1 lg:grid-cols-3 gap-6"
                wire:key="brand-charts-{{ md5(json_encode($brandChartData)) }}"
                x-data="{
                    init() {
                        const data = @js($brandChartData);
                        if (!data.categories || data.categories.length === 0) return;

                        // Bar Chart
                        const barOptions = {
                            chart: {
                                type: 'bar',
                                height: 260,
                                toolbar: { show: false },
                                fontFamily: 'inherit',
                                parentHeightOffset: 0
                            },
                            plotOptions: {
                                bar: {
                                    horizontal: false,
                                    columnWidth: '48%',
                                    borderRadius: 4
                                }
                            },
                            dataLabels: { enabled: false },
                            colors: ['#3b82f6', '#10b981'],
                            series: data.series,
                            xaxis: {
                                categories: data.categories,
                                labels: {
                                    style: { fontSize: '11px', colors: '#64748b' },
                                    rotate: -20,
                                    trim: true,
                                    maxHeight: 60
                                },
                                axisBorder: { show: false },
                                axisTicks: { show: false }
                            },
                            yaxis: {
                                labels: {
                                    style: { fontSize: '11px', colors: '#64748b' },
                                    formatter: (val) => '₹' + Number(val).toLocaleString('en-IN')
                                }
                            },
                            tooltip: {
                                y: {
                                    formatter: (val) => '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 2 })
                                }
                            },
                            legend: {
                                position: 'top',
                                horizontalAlign: 'right',
                                fontSize: '12px'
                            },
                            grid: {
                                borderColor: 'rgba(156, 163, 175, 0.15)',
                                strokeDashArray: 4
                            }
                        };
                        const barChart = new ApexCharts(this.$refs.brandBarChart, barOptions);
                        barChart.render();

                        // Donut Chart
                        if (data.donut && data.donut.series && data.donut.series.length > 0) {
                            const donutOptions = {
                                chart: {
                                    type: 'donut',
                                    height: 260,
                                    fontFamily: 'inherit',
                                    parentHeightOffset: 0
                                },
                                series: data.donut.series.map(Number),
                                labels: data.donut.labels,
                                colors: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#64748b'],
                                dataLabels: { enabled: false },
                                legend: {
                                    position: 'bottom',
                                    fontSize: '11px',
                                    offsetY: 0,
                                    height: 50
                                },
                                tooltip: {
                                    y: {
                                        formatter: (val) => Number(val).toFixed(1) + '%'
                                    }
                                },
                                plotOptions: {
                                    pie: {
                                        donut: {
                                            size: '68%',
                                            labels: {
                                                show: true,
                                                name: { fontSize: '12px' },
                                                value: {
                                                    fontSize: '14px',
                                                    formatter: (val) => Number(val).toFixed(1) + '%'
                                                },
                                                total: {
                                                    show: true,
                                                    label: 'Total Share',
                                                    formatter: (w) => {
                                                        const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                                        return total.toFixed(0) + '%';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            };
                            const donutChart = new ApexCharts(this.$refs.brandDonutChart, donutOptions);
                            donutChart.render();
                        }
                    }
                }"
            >
                <div class="lg:col-span-2 bg-card rounded-lg border border-border p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('Top Brands: Revenue vs Gross Profit') }}</span>
                    </div>
                    <div x-ref="brandBarChart" class="w-full"></div>
                </div>
                <div class="bg-card rounded-lg border border-border p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('Brand Revenue Share %') }}</span>
                    </div>
                    <div x-ref="brandDonutChart" class="w-full flex items-center justify-center"></div>
                </div>
            </div>
        @endif

        <!-- Table View: Clean borderless layout, NO harsh grid -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-foreground">
                <thead class="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('brand_name')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Brand / Category') }}</span>
                                @if($brandSortField === 'brand_name')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('items_count')">
                            <div class="flex items-center justify-center gap-1">
                                <span>{{ __('Items') }}</span>
                                @if($brandSortField === 'items_count')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('quantity')">
                            <div class="flex items-center justify-center gap-1">
                                <span>{{ __('Units Sold') }}</span>
                                @if($brandSortField === 'quantity')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('gross_sales')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Gross Sales') }}</span>
                                @if($brandSortField === 'gross_sales')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('total_discounts')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Discounts') }}</span>
                                @if($brandSortField === 'total_discounts')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right font-bold text-foreground cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('net_sales')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Net Sales') }}</span>
                                @if($brandSortField === 'net_sales')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('percentage')">
                            <div class="flex items-center justify-center gap-1">
                                <span>{{ __('Share') }}</span>
                                @if($brandSortField === 'percentage')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right text-muted-foreground cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('cogs')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('PO Net Cost') }}</span>
                                @if($brandSortField === 'cogs')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right font-bold cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('gross_profit')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Gross Profit') }}</span>
                                @if($brandSortField === 'gross_profit')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByBrand('margin')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Margin') }}</span>
                                @if($brandSortField === 'margin')
                                    <span class="text-primary">{{ $brandSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border border-b border-border">
                    @forelse($brandBreakdown as $b)
                        @php
                            $bProfit = (float) $b['gross_profit'];
                            $bMargin = (float) $b['margin'];
                        @endphp
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    @if(!empty($b['is_service']))
                                        <span class="p-1 rounded bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300">
                                            <x-heroicon-o-sparkles class="w-4 h-4" />
                                        </span>
                                    @else
                                        <span class="p-1 rounded bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                            <x-heroicon-o-building-storefront class="w-4 h-4" />
                                        </span>
                                    @endif
                                    <span class="font-semibold text-foreground">{{ $b['name'] }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-muted-foreground">
                                    {{ $b['items_count'] }} {{ __('items') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap font-medium text-foreground">
                                {{ number_format($b['total_quantity']) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono text-muted-foreground">
                                ₹ {{ number_format($b['gross_sales'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono">
                                @if($b['total_discounts'] > 0)
                                    <span class="text-amber-600 dark:text-amber-400 font-medium">-₹ {{ number_format($b['total_discounts'], 2) }}</span>
                                @else
                                    <span class="text-muted-foreground/60">₹ 0.00</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono font-bold text-foreground">
                                ₹ {{ number_format($b['net_sales'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary">
                                    {{ number_format($b['percentage'], 1) }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono text-muted-foreground">
                                ₹ {{ number_format($b['cogs'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono font-bold {{ $bProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                ₹ {{ number_format($bProfit, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $bMargin >= 25 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : ($bMargin >= 10 ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300' : ($bMargin >= 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300')) }}">
                                    {{ number_format($bMargin, 1) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">
                                {{ __('No brand sales recorded in this period.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Brands Pagination Footer -->
        <div class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground bg-muted/20">
            <div>
                {{ __('Showing') }} <span class="font-semibold text-foreground">{{ $brandBreakdown->firstItem() ?? 0 }}</span>
                {{ __('to') }} <span class="font-semibold text-foreground">{{ $brandBreakdown->lastItem() ?? 0 }}</span>
                {{ __('of') }} <span class="font-semibold text-foreground">{{ $brandBreakdown->total() }}</span> {{ __('brands') }}
                @if($brandSearch)
                    <span class="text-primary font-medium">({{ __('filtered from') }} {{ $allBrands->count() }})</span>
                @endif
            </div>

            @if($brandBreakdown->hasPages())
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        wire:click="previousPage('brandsPage')"
                        @disabled($brandBreakdown->onFirstPage())
                        class="px-2.5 py-1 rounded border border-border bg-card text-foreground disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted text-xs font-medium cursor-pointer"
                    >
                        {{ __('Previous') }}
                    </button>
                    <span class="px-2 font-mono text-xs">
                        {{ $brandBreakdown->currentPage() }} / {{ $brandBreakdown->lastPage() }}
                    </span>
                    <button
                        type="button"
                        wire:click="nextPage('brandsPage')"
                        @disabled(!$brandBreakdown->hasMorePages())
                        class="px-2.5 py-1 rounded border border-border bg-card text-foreground disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted text-xs font-medium cursor-pointer"
                    >
                        {{ __('Next') }}
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Sales by Item Container -->
    <div
        x-show="activeTab === 'items' || activeTab === 'all'"
        x-cloak
        wire:loading.class="opacity-60 transition-opacity duration-150"
        wire:target="dateFilter, startDate, endDate, itemSearch, itemBrandFilter, itemTypeFilter, itemPerPage, sortByItem"
        class="bg-card border border-border rounded-xl shadow-sm overflow-hidden"
        x-data="{ expandAllItems: false }"
    >
        <!-- Header & Controls -->
        <div class="p-5 border-b border-border flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-cube class="w-5 h-5 text-primary" />
                    <span>{{ __('Sales by Item') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('Itemized sales performance, PO net unit cost, margins, and related invoice breakdown across all products and services.') }}
                </p>
            </div>
            <!-- Server-Side Controls & Exports -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div
                    wire:loading.flex
                    wire:target="itemSearch, itemBrandFilter, itemTypeFilter, itemPerPage, sortByItem"
                    class="items-center gap-1.5 px-2.5 py-1 rounded-lg bg-primary/10 text-primary text-xs font-semibold border border-primary/20"
                >
                    <svg class="w-3.5 h-3.5 animate-spin text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __('Fetching...') }}</span>
                </div>

                <!-- Search -->
                <div class="relative min-w-[180px] sm:min-w-[210px]">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="itemSearch"
                        placeholder="{{ __('Search item or SKU...') }}"
                        class="w-full pl-9 pr-7 py-1.5 text-xs rounded-lg border border-border bg-background focus:ring-1 focus:ring-primary focus:outline-none placeholder:text-muted-foreground"
                    />
                    @if($itemSearch)
                        <button type="button" wire:click="$set('itemSearch', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                            <x-heroicon-s-x-mark class="w-3.5 h-3.5" />
                        </button>
                    @endif
                </div>

                <!-- Brand Filter -->
                <select
                    wire:model.live="itemBrandFilter"
                    class="text-xs py-1.5 px-2.5 rounded-lg border border-border bg-background text-foreground focus:ring-1 focus:ring-primary focus:outline-none cursor-pointer"
                >
                    <option value="all">{{ __('All Brands') }}</option>
                    @foreach($brandOptions as $brandOpt)
                        <option value="{{ $brandOpt }}">{{ $brandOpt }}</option>
                    @endforeach
                </select>

                <!-- Type Filter -->
                <select
                    wire:model.live="itemTypeFilter"
                    class="text-xs py-1.5 px-2.5 rounded-lg border border-border bg-background text-foreground focus:ring-1 focus:ring-primary focus:outline-none cursor-pointer"
                >
                    <option value="all">{{ __('All Types') }}</option>
                    <option value="products">{{ __('Products') }}</option>
                    <option value="services">{{ __('Services') }}</option>
                </select>

                <!-- Per Page -->
                <select
                    wire:model.live="itemPerPage"
                    class="text-xs py-1.5 px-2.5 rounded-lg border border-border bg-background text-foreground focus:ring-1 focus:ring-primary focus:outline-none cursor-pointer"
                >
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="all">{{ __('All') }}</option>
                </select>

                @if($allItems->isNotEmpty())
                    <button
                        type="button"
                        @click="expandAllItems = !expandAllItems; $dispatch('toggle-all-items', { expanded: expandAllItems })"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-muted/40 hover:bg-muted text-xs font-semibold text-foreground transition-colors cursor-pointer"
                    >
                        <x-heroicon-o-arrows-up-down class="w-3.5 h-3.5 text-muted-foreground" />
                        <span x-text="expandAllItems ? '{{ __('Collapse All') }}' : '{{ __('Expand All') }}'"></span>
                    </button>
                @endif

                <!-- Export XLS -->
                <a
                    href="{{ route('reports.sales.xls', ['start_date' => $startDate, 'end_date' => $endDate, 'section' => 'items']) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800 transition-colors"
                    title="{{ __('Download Items Excel Spreadsheet') }}"
                >
                    <x-heroicon-o-table-cells class="w-3.5 h-3.5" />
                    <span>{{ __('XLS') }}</span>
                </a>

                <!-- Export PDF -->
                <a
                    href="{{ route('reports.sales.pdf', ['start_date' => $startDate, 'end_date' => $endDate, 'section' => 'items']) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/70 border border-red-200 dark:border-red-800 transition-colors"
                    title="{{ __('Download Items PDF') }}"
                >
                    <x-heroicon-o-document-arrow-down class="w-3.5 h-3.5" />
                    <span>{{ __('PDF') }}</span>
                </a>
            </div>
        </div>

        <!-- Item Visual Chart -->
        @if(!empty($itemChartData['categories']))
            <div
                class="p-5 border-b border-border bg-muted/10"
                wire:key="item-charts-{{ md5(json_encode($itemChartData)) }}"
                x-data="{
                    init() {
                        const data = @js($itemChartData);
                        if (!data.categories || data.categories.length === 0) return;

                        const barOptions = {
                            chart: {
                                type: 'bar',
                                height: Math.max(260, data.categories.length * 30),
                                toolbar: { show: false },
                                fontFamily: 'inherit',
                                parentHeightOffset: 0
                            },
                            plotOptions: {
                                bar: {
                                    horizontal: true,
                                    barHeight: '60%',
                                    borderRadius: 4
                                }
                            },
                            dataLabels: { enabled: false },
                            colors: ['#3b82f6', '#10b981'],
                            series: data.series,
                            xaxis: {
                                categories: data.categories,
                                labels: {
                                    style: { fontSize: '11px', colors: '#64748b' },
                                    formatter: (val) => '₹' + Number(val).toLocaleString('en-IN')
                                },
                                axisBorder: { show: false },
                                axisTicks: { show: false }
                            },
                            yaxis: {
                                labels: {
                                    style: { fontSize: '11px', colors: '#64748b' },
                                    maxWidth: 220
                                }
                            },
                            tooltip: {
                                y: {
                                    formatter: (val) => '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 2 })
                                }
                            },
                            legend: {
                                position: 'top',
                                horizontalAlign: 'right',
                                fontSize: '12px'
                            },
                            grid: {
                                borderColor: 'rgba(156, 163, 175, 0.15)',
                                strokeDashArray: 4
                            }
                        };
                        const chart = new ApexCharts(this.$refs.itemBarChart, barOptions);
                        chart.render();
                    }
                }"
            >
                <div class="bg-card rounded-lg border border-border p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('Top Selling Items: Net Revenue vs Gross Profit') }}</span>
                    </div>
                    <div x-ref="itemBarChart" class="w-full"></div>
                </div>
            </div>
        @endif

        <!-- Table View: Clean borderless layout, NO harsh grid -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-foreground">
                <thead class="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('name')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Product / Item Name') }}</span>
                                @if($itemSortField === 'name')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('brand_name')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Brand') }}</span>
                                @if($itemSortField === 'brand_name')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('is_service')">
                            <div class="flex items-center justify-center gap-1">
                                <span>{{ __('Type') }}</span>
                                @if($itemSortField === 'is_service')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('quantity')">
                            <div class="flex items-center justify-center gap-1">
                                <span>{{ __('Qty Sold') }}</span>
                                @if($itemSortField === 'quantity')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('unit_price')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Unit MRP') }}</span>
                                @if($itemSortField === 'unit_price')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('total_discounts')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Discounts') }}</span>
                                @if($itemSortField === 'total_discounts')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right font-bold text-foreground cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('net_sales')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Net Sales') }}</span>
                                @if($itemSortField === 'net_sales')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right text-muted-foreground cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('cogs')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('PO Net Cost') }}</span>
                                @if($itemSortField === 'cogs')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right font-bold cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('gross_profit')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Gross Profit') }}</span>
                                @if($itemSortField === 'gross_profit')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('margin')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Margin') }}</span>
                                @if($itemSortField === 'margin')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByItem('invoices_count')">
                            <div class="flex items-center justify-center gap-1">
                                <span>{{ __('Invoices') }}</span>
                                @if($itemSortField === 'invoices_count')
                                    <span class="text-primary">{{ $itemSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                    </tr>
                </thead>
                @forelse($itemBreakdown as $item)
                    @php
                        $iProfit = (float) $item['gross_profit'];
                        $iMargin = (float) $item['margin'];
                        $relatedInvoices = $item['invoice_breakdown'] ?? [];
                    @endphp
                    <tbody
                        x-data="{ expandedItem: false }"
                        x-on:toggle-all-items.window="expandedItem = $event.detail.expanded"
                        class="divide-y divide-border border-b border-border"
                    >
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <button type="button" @click="expandedItem = !expandedItem" class="hover:underline flex items-start gap-2 text-left group cursor-pointer">
                                    <svg class="w-3.5 h-3.5 mt-1 shrink-0 transition-transform duration-200 text-muted-foreground group-hover:text-primary" :class="{ 'rotate-90 text-primary': expandedItem }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <div>
                                        <div class="font-medium text-foreground group-hover:text-primary transition-colors">{{ $item['name'] }}</div>
                                        @if(!empty($item['code']) && $item['code'] !== '-')
                                            <div class="text-[11px] font-mono text-muted-foreground">{{ $item['code'] }}</div>
                                        @endif
                                    </div>
                                </button>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-muted text-foreground">
                                    {{ $item['brand_name'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if(!empty($item['is_service']))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300">
                                        {{ __('Service') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                        {{ __('Product') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap font-medium text-foreground">
                                {{ number_format($item['quantity']) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono text-muted-foreground">
                                ₹ {{ number_format($item['unit_price'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono">
                                @if($item['total_discounts'] > 0)
                                    <span class="text-amber-600 dark:text-amber-400 font-medium">-₹ {{ number_format($item['total_discounts'], 2) }}</span>
                                @else
                                    <span class="text-muted-foreground/60">₹ 0.00</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono font-bold text-foreground">
                                ₹ {{ number_format($item['net_sales'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono text-muted-foreground" title="{{ $item['po_source'] }}: ₹ {{ number_format($item['po_unit_price'], 2) }}/unit">
                                ₹ {{ number_format($item['cogs'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-mono font-bold {{ $iProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                ₹ {{ number_format($iProfit, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $iMargin >= 25 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : ($iMargin >= 10 ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300' : ($iMargin >= 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300')) }}">
                                    {{ number_format($iMargin, 1) }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button
                                    type="button"
                                    @click="expandedItem = !expandedItem"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium border border-border bg-card hover:bg-muted text-foreground transition-colors cursor-pointer"
                                    title="{{ __('View related invoices breakdown') }}"
                                >
                                    <span class="font-mono font-semibold text-primary">{{ $item['invoices_count'] }}</span>
                                    <span class="text-muted-foreground">{{ \Illuminate\Support\Str::plural('invoice', $item['invoices_count']) }}</span>
                                    <svg class="w-3 h-3 transition-transform duration-200 text-muted-foreground" :class="{ 'rotate-180 text-primary': expandedItem }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <!-- Expandable Related Invoices Breakdown Row (Matched to Completed Invoices in Period) -->
                        <tr x-show="expandedItem" x-cloak style="display: none;" class="bg-muted/15">
                            <td colspan="11" class="p-4">
                                <div class="rounded-lg border border-border bg-card p-3.5 shadow-xs space-y-3">
                                    <!-- Drawer Header -->
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border pb-2 text-xs">
                                        <div class="font-semibold text-foreground flex items-center gap-1.5">
                                            <x-heroicon-o-receipt-percent class="w-4 h-4 text-primary" />
                                            <span>{{ __('Related Invoices Breakdown for') }} <span class="font-bold text-primary">{{ $item['name'] }}</span></span>
                                            <span class="ml-1 px-2 py-0.5 rounded bg-muted text-muted-foreground text-[11px] font-medium">
                                                {{ $item['invoices_count'] }} {{ \Illuminate\Support\Str::plural('invoice', $item['invoices_count']) }} · {{ number_format($item['quantity']) }} {{ \Illuminate\Support\Str::plural('unit', $item['quantity']) }}
                                            </span>
                                        </div>
                                        <div class="text-muted-foreground font-mono text-[11px]">
                                            {{ __('Formula: Net Sales') }} (₹{{ number_format($item['net_sales'], 2) }}) - {{ __('PO Net Cost') }} (₹{{ number_format($item['cogs'], 2) }}) = <span class="font-bold {{ $iProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">₹{{ number_format($iProfit, 2) }} ({{ number_format($iMargin, 1) }}%)</span>
                                        </div>
                                    </div>

                                    <!-- Related Invoices Table (Same columns & style as Completed Invoices in Period) -->
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs text-left">
                                            <thead class="bg-muted/50 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground border-b border-border">
                                                <tr>
                                                    <th class="py-2 px-3">{{ __('Invoice #') }}</th>
                                                    <th class="py-2 px-3">{{ __('Date') }}</th>
                                                    <th class="py-2 px-3">{{ __('Customer') }}</th>
                                                    <th class="py-2 px-3 text-center">{{ __('Qty Sold') }}</th>
                                                    <th class="py-2 px-3 text-center">{{ __('Payment Mode') }}</th>
                                                    <th class="py-2 px-3 text-right">{{ __('Gross') }}</th>
                                                    <th class="py-2 px-3 text-right">{{ __('Discount') }}</th>
                                                    <th class="py-2 px-3 text-right font-bold text-foreground">{{ __('Net Sales') }}</th>
                                                    <th class="py-2 px-3 text-right text-muted-foreground">{{ __('Items Net Cost') }}</th>
                                                    <th class="py-2 px-3 text-right font-bold">{{ __('Gross Profit') }}</th>
                                                    <th class="py-2 px-3 text-center">{{ __('Action') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-border">
                                                @foreach($relatedInvoices as $inv)
                                                    @php
                                                        $invMethodVal = $inv['payment_method'] ?? 'cash';
                                                        $invBadgeClass = match($invMethodVal) {
                                                            'upi' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300',
                                                            'card' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300',
                                                            'cash' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300',
                                                            'transfer' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300',
                                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
                                                        };
                                                        $invProfit = (float) ($inv['gross_profit'] ?? 0);
                                                        $invMargin = (float) ($inv['margin'] ?? 0);
                                                    @endphp
                                                    <tr class="hover:bg-muted/30 transition-colors">
                                                        <td class="py-2.5 px-3 font-mono font-bold text-primary whitespace-nowrap">
                                                            <a href="{{ route('sales.show', $inv['sale_id']) }}" class="hover:underline inline-flex items-center gap-1">
                                                                <span>{{ $inv['invoice_number'] }}</span>
                                                            </a>
                                                        </td>
                                                        <td class="py-2.5 px-3 whitespace-nowrap text-muted-foreground">
                                                            {{ $inv['sale_date'] }}
                                                        </td>
                                                        <td class="py-2.5 px-3 whitespace-nowrap">
                                                            <div class="font-medium text-foreground">{{ $inv['customer_name'] }}</div>
                                                            @if(!empty($inv['customer_phone']))
                                                                <div class="text-[11px] text-muted-foreground">{{ $inv['customer_phone'] }}</div>
                                                            @endif
                                                        </td>
                                                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-muted text-foreground font-medium text-[11px]">
                                                                {{ number_format($inv['quantity']) }} {{ !empty($item['is_service']) ? \Illuminate\Support\Str::plural('service', $inv['quantity']) : \Illuminate\Support\Str::plural('product', $inv['quantity']) }}
                                                            </span>
                                                        </td>
                                                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $invBadgeClass }}">
                                                                {{ $inv['payment_label'] }}
                                                            </span>
                                                        </td>
                                                        <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono text-muted-foreground">
                                                            ₹ {{ number_format($inv['gross'], 2) }}
                                                        </td>
                                                        <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono text-amber-600 dark:text-amber-400">
                                                            @if($inv['discount'] > 0)
                                                                -₹ {{ number_format($inv['discount'], 2) }}
                                                            @else
                                                                <span class="text-muted-foreground opacity-60">₹ 0.00</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-bold text-foreground">
                                                            ₹ {{ number_format($inv['net_received'], 2) }}
                                                        </td>
                                                        <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono text-muted-foreground" title="{{ $inv['po_source'] }}: ₹ {{ number_format($inv['po_unit_price'], 2) }}/unit">
                                                            ₹ {{ number_format($inv['cogs'], 2) }}
                                                        </td>
                                                        <td class="py-2.5 px-3 text-right whitespace-nowrap font-mono font-bold {{ $invProfit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                            <div>₹ {{ number_format($invProfit, 2) }}</div>
                                                            <div class="text-[10px] font-sans font-normal opacity-75">
                                                                {{ number_format($invMargin, 1) }}% margin
                                                            </div>
                                                        </td>
                                                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                                            <a href="{{ route('sales.show', $inv['sale_id']) }}" class="inline-flex items-center text-xs text-primary hover:underline font-medium">
                                                                <x-heroicon-o-eye class="w-3.5 h-3.5 mr-0.5" />
                                                                {{ __('View') }}
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="bg-muted/40 font-bold border-t border-border text-[11px] text-foreground">
                                                <tr>
                                                    <td colspan="3" class="py-2 px-3 uppercase text-muted-foreground">
                                                        {{ __('Total across') }} {{ $item['invoices_count'] }} {{ \Illuminate\Support\Str::plural('invoice', $item['invoices_count']) }}
                                                    </td>
                                                    <td class="py-2 px-3 text-center">{{ number_format($item['quantity']) }}</td>
                                                    <td class="py-2 px-3 text-center text-muted-foreground">-</td>
                                                    <td class="py-2 px-3 text-right font-mono">₹ {{ number_format($item['gross_sales'], 2) }}</td>
                                                    <td class="py-2 px-3 text-right font-mono text-amber-600 dark:text-amber-400">
                                                        @if($item['total_discounts'] > 0)
                                                            -₹ {{ number_format($item['total_discounts'], 2) }}
                                                        @else
                                                            ₹ 0.00
                                                        @endif
                                                    </td>
                                                    <td class="py-2 px-3 text-right font-mono font-bold text-foreground">₹ {{ number_format($item['net_sales'], 2) }}</td>
                                                    <td class="py-2 px-3 text-right font-mono text-muted-foreground">₹ {{ number_format($item['cogs'], 2) }}</td>
                                                    <td class="py-2 px-3 text-right font-mono font-bold {{ $iProfit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                        ₹ {{ number_format($iProfit, 2) }} ({{ number_format($iMargin, 1) }}%)
                                                    </td>
                                                    <td class="py-2 px-3"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-muted-foreground">
                                {{ __('No items sold matching your criteria.') }}
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        <!-- Items Pagination Footer -->
        <div class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground bg-muted/20">
            <div>
                {{ __('Showing') }} <span class="font-semibold text-foreground">{{ $itemBreakdown->firstItem() ?? 0 }}</span>
                {{ __('to') }} <span class="font-semibold text-foreground">{{ $itemBreakdown->lastItem() ?? 0 }}</span>
                {{ __('of') }} <span class="font-semibold text-foreground">{{ $itemBreakdown->total() }}</span> {{ __('items') }}
                @if($itemSearch || $itemBrandFilter !== 'all' || $itemTypeFilter !== 'all')
                    <span class="text-primary font-medium">({{ __('filtered from') }} {{ $allItems->count() }})</span>
                @endif
            </div>

            @if($itemBreakdown->hasPages())
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        wire:click="previousPage('itemsPage')"
                        @disabled($itemBreakdown->onFirstPage())
                        class="px-2.5 py-1 rounded border border-border bg-card text-foreground disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted text-xs font-medium cursor-pointer"
                    >
                        {{ __('Previous') }}
                    </button>
                    <span class="px-2 font-mono text-xs">
                        {{ $itemBreakdown->currentPage() }} / {{ $itemBreakdown->lastPage() }}
                    </span>
                    <button
                        type="button"
                        wire:click="nextPage('itemsPage')"
                        @disabled(!$itemBreakdown->hasMorePages())
                        class="px-2.5 py-1 rounded border border-border bg-card text-foreground disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted text-xs font-medium cursor-pointer"
                    >
                        {{ __('Next') }}
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Completed Invoices in Period (Transparent, Reconciled Math: Cash Received - Items Net Cost = Profit) -->
    <div
        x-show="activeTab === 'invoices' || activeTab === 'all'"
        x-cloak
        wire:loading.class="opacity-60 transition-opacity duration-150"
        wire:target="dateFilter, startDate, endDate, invoiceSearch, invoicePaymentMethod, invoicePerPage, sortByInvoice"
        class="bg-card border border-border rounded-xl shadow-sm overflow-hidden"
    >
        <div class="p-5 border-b border-border flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-receipt-percent class="w-5 h-5 text-primary" />
                    <span>{{ __('Completed Invoices in Period') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('Itemized transaction audit: Gross, Discount, Cash Received, Items Net Purchase Cost (MRP - vendor discount), and Profit.') }}
                </p>
            </div>
            <!-- Server-Side Controls & Exports -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div
                    wire:loading.flex
                    wire:target="invoiceSearch, invoicePaymentMethod, invoicePerPage, sortByInvoice"
                    class="items-center gap-1.5 px-2.5 py-1 rounded-lg bg-primary/10 text-primary text-xs font-semibold border border-primary/20"
                >
                    <svg class="w-3.5 h-3.5 animate-spin text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __('Fetching...') }}</span>
                </div>

                <!-- Search -->
                <div class="relative min-w-[200px] sm:min-w-[240px]">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="invoiceSearch"
                        placeholder="{{ __('Search invoice #, customer, item...') }}"
                        class="w-full pl-9 pr-7 py-1.5 text-xs rounded-lg border border-border bg-background focus:ring-1 focus:ring-primary focus:outline-none placeholder:text-muted-foreground"
                    />
                    @if($invoiceSearch)
                        <button type="button" wire:click="$set('invoiceSearch', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                            <x-heroicon-s-x-mark class="w-3.5 h-3.5" />
                        </button>
                    @endif
                </div>

                <!-- Payment Method Filter -->
                <select
                    wire:model.live="invoicePaymentMethod"
                    class="text-xs py-1.5 px-2.5 rounded-lg border border-border bg-background text-foreground focus:ring-1 focus:ring-primary focus:outline-none"
                >
                    <option value="all">{{ __('All Modes') }}</option>
                    <option value="cash">{{ __('Cash') }}</option>
                    <option value="upi">{{ __('UPI') }}</option>
                    <option value="card">{{ __('Card') }}</option>
                    <option value="transfer">{{ __('Transfer') }}</option>
                </select>

                <!-- Per Page -->
                <select
                    wire:model.live="invoicePerPage"
                    class="text-xs py-1.5 px-2.5 rounded-lg border border-border bg-background text-foreground focus:ring-1 focus:ring-primary focus:outline-none"
                >
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                    <option value="all">{{ __('All') }}</option>
                </select>

                <!-- Export XLS -->
                <a
                    href="{{ route('reports.sales.xls', ['start_date' => $startDate, 'end_date' => $endDate, 'section' => 'invoices']) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800 transition-colors"
                    title="{{ __('Download Invoices Excel Spreadsheet') }}"
                >
                    <x-heroicon-o-table-cells class="w-3.5 h-3.5" />
                    <span>{{ __('XLS') }}</span>
                </a>

                <!-- Export PDF -->
                <a
                    href="{{ route('reports.sales.pdf', ['start_date' => $startDate, 'end_date' => $endDate, 'section' => 'invoices']) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/70 border border-red-200 dark:border-red-800 transition-colors"
                    title="{{ __('Download Invoices PDF') }}"
                >
                    <x-heroicon-o-document-arrow-down class="w-3.5 h-3.5" />
                    <span>{{ __('PDF') }}</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto max-h-[36rem]">
            <table class="w-full text-left text-sm text-foreground">
                <thead class="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground sticky top-0 border-b border-border z-10">
                    <tr>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByInvoice('invoice_number')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Invoice #') }}</span>
                                @if($invoiceSortField === 'invoice_number')
                                    <span class="text-primary">{{ $invoiceSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByInvoice('sale_date')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Date') }}</span>
                                @if($invoiceSortField === 'sale_date')
                                    <span class="text-primary">{{ $invoiceSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByInvoice('customer')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Customer') }}</span>
                                @if($invoiceSortField === 'customer')
                                    <span class="text-primary">{{ $invoiceSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3">{{ __('Items Type') }}</th>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByInvoice('payment_method')">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Payment Mode') }}</span>
                                @if($invoiceSortField === 'payment_method')
                                    <span class="text-primary">{{ $invoiceSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByInvoice('subtotal')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Gross') }}</span>
                                @if($invoiceSortField === 'subtotal')
                                    <span class="text-primary">{{ $invoiceSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByInvoice('discount')">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Discount') }}</span>
                                @if($invoiceSortField === 'discount')
                                    <span class="text-primary">{{ $invoiceSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right font-bold text-foreground cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByInvoice('total')" title="Total amount collected from customer">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Cash Received') }}</span>
                                @if($invoiceSortField === 'total')
                                    <span class="text-primary">{{ $invoiceSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right text-muted-foreground cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByInvoice('cogs')" title="Sum of Net Purchase Prices (MRP - Vendor Discount) of items in invoice">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Items Net Cost') }}</span>
                                @if($invoiceSortField === 'cogs')
                                    <span class="text-primary">{{ $invoiceSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right font-bold cursor-pointer select-none hover:text-foreground transition-colors" wire:click="sortByInvoice('gross_profit')" title="Cash Received minus Items Net Cost">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Gross Profit') }}</span>
                                @if($invoiceSortField === 'gross_profit')
                                    <span class="text-primary">{{ $invoiceSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @else
                                    <span class="opacity-30 text-[10px]">▲▼</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center">{{ __('Audit / Action') }}</th>
                    </tr>
                </thead>
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

                        $itemDiscountsSum = (float) $sale->items->sum(fn($i) => ((float)$i->discount) * ((int)$i->quantity));
                        $netItemsSubtotal = (float) $sale->items->sum(fn($i) => (float)$i->subtotal);
                        $totalQty = (int) $sale->items->sum(fn($i) => (int)$i->quantity);
                        $totalGrossItems = (float) $sale->items->sum(fn($i) => ((float)$i->unit_price) * ((int)$i->quantity));

                        $saleCouponDiscount = (float) ($sale->coupon_discount ?? 0);
                        if ($saleCouponDiscount == 0 && !empty($sale->coupon_code) && (float)($sale->global_discount ?? 0) > 0 && (float)($sale->round_off ?? 0) == 0) {
                            $saleCouponDiscount = (float) $sale->global_discount;
                        }
                        $saleBillDiscount = max(0.0, round(((float) ($sale->global_discount ?? 0)) - $saleCouponDiscount, 2));
                        $saleRoundOff = (float) ($sale->round_off ?? 0);
                        if ($saleRoundOff == 0 && empty($sale->coupon_code) && (float)($sale->global_discount ?? 0) != 0) {
                            $saleRoundOff = -((float) $sale->global_discount);
                        }
                    @endphp
                    <tbody x-data="{ expanded: false }" class="divide-y divide-border border-b border-border">
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 font-semibold text-primary whitespace-nowrap">
                                <button type="button" @click="expanded = !expanded" class="hover:underline flex items-center gap-1.5 text-left font-mono font-bold text-primary">
                                    <svg class="w-3.5 h-3.5 transition-transform duration-200 text-muted-foreground" :class="{ 'rotate-90 text-primary': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <span>{{ $sale->invoice_number }}</span>
                                </button>
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
                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" @click="expanded = !expanded" class="inline-flex items-center text-xs text-muted-foreground hover:text-primary transition-colors">
                                        <span x-text="expanded ? 'Hide' : 'Items'"></span>
                                    </button>
                                    <span class="text-border">|</span>
                                    <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center text-xs text-primary hover:underline font-medium">
                                        <x-heroicon-o-eye class="w-3.5 h-3.5 mr-0.5" />
                                        {{ __('View') }}
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- Expandable Item-by-Item Net Price Breakdown Row -->
                        <tr x-show="expanded" x-cloak style="display: none;" class="bg-muted/15">
                            <td colspan="11" class="p-4">
                                <div class="rounded-lg border border-border bg-card p-3 shadow-xs space-y-3">
                                    <!-- Drawer Header -->
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border pb-2 text-xs">
                                        <div class="font-semibold text-foreground flex items-center gap-1.5">
                                            <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-primary" />
                                            <span>{{ __('Line Items & PO Net Price Audit for') }} {{ $sale->invoice_number }}</span>
                                        </div>
                                        <div class="text-muted-foreground font-mono">
                                            {{ __('Formula: Cash Received') }} (₹{{ number_format((float) $sale->total, 2) }}) - {{ __('Items Net Cost') }} (₹{{ number_format($cogs, 2) }}) = <span class="font-bold {{ $profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">₹{{ number_format($profit, 2) }}</span>
                                        </div>
                                    </div>

                                    <!-- Items Detail Table -->
                                    <table class="w-full text-xs">
                                        <thead class="bg-muted/50 text-[11px] uppercase tracking-wider text-muted-foreground border-b border-border">
                                            <tr>
                                                <th class="py-1.5 px-2 text-left">{{ __('Item Name') }}</th>
                                                <th class="py-1.5 px-2 text-center">{{ __('Type') }}</th>
                                                <th class="py-1.5 px-2 text-center">{{ __('Qty') }}</th>
                                                <th class="py-1.5 px-2 text-right">{{ __('MRP / Unit') }}</th>
                                                <th class="py-1.5 px-2 text-right">{{ __('Discount') }}</th>
                                                <th class="py-1.5 px-2 text-right">{{ __('Net Received') }}</th>
                                                <th class="py-1.5 px-2 text-right text-muted-foreground">{{ __('PO Net Price') }}</th>
                                                <th class="py-1.5 px-2 text-right text-muted-foreground">{{ __('Total Cost') }}</th>
                                                <th class="py-1.5 px-2 text-right font-bold">{{ __('Line Profit') }}</th>
                                                <th class="py-1.5 px-2 text-left">{{ __('PO / Cost Source') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border">
                                            @foreach($sale->items as $item)
                                                @php
                                                    $isServ = !empty($item->service_id);
                                                    $itemQty = (int) $item->quantity;
                                                    $lineSubtotal = (float) $item->subtotal;
                                                    $itemPoUnit = (float) ($item->po_unit_price ?? 0);
                                                    $itemPoCost = (float) ($item->po_cost_total ?? 0);
                                                    $lineProfit = round($lineSubtotal - $itemPoCost, 2);
                                                    $sourceDesc = $item->po_source ?? ($isServ ? 'Service (0 COGS)' : 'Catalog price');
                                                @endphp
                                                <tr class="hover:bg-muted/30">
                                                    <td class="py-1.5 px-2 font-medium text-foreground">
                                                        {{ $item->item_name }}
                                                    </td>
                                                    <td class="py-1.5 px-2 text-center">
                                                        @if($isServ)
                                                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 font-medium">Service</span>
                                                        @else
                                                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-medium">Product</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-1.5 px-2 text-center font-semibold">{{ $itemQty }}</td>
                                                    <td class="py-1.5 px-2 text-right">
                                                        <div>₹ {{ number_format((float) $item->unit_price, 2) }}</div>
                                                        @if($itemQty > 1)
                                                            <div class="text-[10px] text-muted-foreground font-mono">
                                                                Total: ₹ {{ number_format(((float) $item->unit_price) * $itemQty, 2) }}
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="py-1.5 px-2 text-right text-amber-600 dark:text-amber-400">
                                                        @php
                                                            $lineItemDiscount = ((float) $item->discount) * $itemQty;
                                                        @endphp
                                                        @if($lineItemDiscount > 0)
                                                            <div class="font-semibold">-₹ {{ number_format($lineItemDiscount, 2) }}</div>
                                                            @if($itemQty > 1 && (float) $item->discount > 0)
                                                                <div class="text-[10px] text-muted-foreground font-normal">
                                                                    (-₹ {{ number_format((float) $item->discount, 2) }}/u)
                                                                </div>
                                                            @endif
                                                        @else
                                                            <span class="text-muted-foreground opacity-60">₹ 0.00</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-1.5 px-2 text-right font-semibold">₹ {{ number_format($lineSubtotal, 2) }}</td>
                                                    <td class="py-1.5 px-2 text-right text-muted-foreground font-mono">
                                                        ₹ {{ number_format($itemPoUnit, 2) }}
                                                    </td>
                                                    <td class="py-1.5 px-2 text-right text-muted-foreground font-mono">
                                                        ₹ {{ number_format($itemPoCost, 2) }}
                                                    </td>
                                                    <td class="py-1.5 px-2 text-right font-bold {{ $lineProfit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                        ₹ {{ number_format($lineProfit, 2) }}
                                                    </td>
                                                    <td class="py-1.5 px-2 text-muted-foreground text-[11px] truncate max-w-xs" title="{{ $sourceDesc }}">
                                                        {{ $sourceDesc }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-muted/40 font-semibold border-t-2 border-border text-[11px] text-foreground">
                                            <tr>
                                                <td class="py-1.5 px-2 uppercase text-muted-foreground">{{ __('Items Total') }}</td>
                                                <td class="py-1.5 px-2 text-center text-muted-foreground">{{ count($sale->items) }} {{ \Illuminate\Support\Str::plural('line', count($sale->items)) }}</td>
                                                <td class="py-1.5 px-2 text-center font-bold">{{ $totalQty }}</td>
                                                <td class="py-1.5 px-2 text-right">₹ {{ number_format($totalGrossItems, 2) }}</td>
                                                <td class="py-1.5 px-2 text-right text-amber-600 dark:text-amber-400">
                                                    @if($itemDiscountsSum > 0)
                                                        -₹ {{ number_format($itemDiscountsSum, 2) }}
                                                    @else
                                                        ₹ 0.00
                                                    @endif
                                                </td>
                                                <td class="py-1.5 px-2 text-right font-bold text-foreground">₹ {{ number_format($netItemsSubtotal, 2) }}</td>
                                                <td class="py-1.5 px-2 text-right text-muted-foreground">-</td>
                                                <td class="py-1.5 px-2 text-right text-muted-foreground font-mono font-bold">₹ {{ number_format($cogs, 2) }}</td>
                                                <td class="py-1.5 px-2 text-right font-bold {{ ($netItemsSubtotal - $cogs) >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                    ₹ {{ number_format($netItemsSubtotal - $cogs, 2) }}
                                                </td>
                                                <td class="py-1.5 px-2 text-muted-foreground text-[10px]">{{ __('Sum of line items') }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>

                                    <!-- Dedicated Invoice Bill Adjustments & Profit Summary Bar -->
                                    <div class="rounded-md border border-border/80 bg-muted/20 p-3">
                                        <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-2 flex items-center justify-between">
                                            <span class="flex items-center gap-1.5">
                                                <x-heroicon-o-calculator class="w-3.5 h-3.5 text-primary" />
                                                <span>{{ __('Invoice Bill Reconciliation & Profit Math') }}</span>
                                            </span>
                                            <span class="font-mono text-[10px] font-normal lowercase text-muted-foreground">
                                                {{ __('items subtotal - coupon - other discounts + round off = cash received') }}
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5 text-xs">
                                            <!-- Items Subtotal -->
                                            <div class="rounded border border-border bg-card p-2">
                                                <div class="text-[10px] text-muted-foreground">{{ __('Items Subtotal') }}</div>
                                                <div class="font-bold text-foreground font-mono text-sm mt-0.5">
                                                    ₹ {{ number_format($netItemsSubtotal, 2) }}
                                                </div>
                                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                                    {{ __('After item discounts') }}
                                                </div>
                                            </div>

                                            <!-- Coupon Discount -->
                                            <div class="rounded border border-border bg-card p-2 {{ $saleCouponDiscount > 0 ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/60 dark:bg-emerald-950/20' : '' }}">
                                                <div class="text-[10px] flex items-center justify-between text-muted-foreground">
                                                    <span>{{ __('Coupon Discount') }}</span>
                                                    @if($sale->coupon_code)
                                                        <span class="text-[9px] font-bold uppercase px-1 py-0.2 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300">
                                                            {{ $sale->coupon_code }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="font-bold font-mono text-sm mt-0.5 {{ $saleCouponDiscount > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground' }}">
                                                    @if($saleCouponDiscount > 0)
                                                        -₹ {{ number_format($saleCouponDiscount, 2) }}
                                                    @else
                                                        ₹ 0.00
                                                    @endif
                                                </div>
                                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                                    {{ $saleCouponDiscount > 0 ? __('Promo coupon applied') : __('No coupon') }}
                                                </div>
                                            </div>

                                            <!-- Other / Bill Discount -->
                                            <div class="rounded border border-border bg-card p-2 {{ $saleBillDiscount > 0 ? 'border-amber-200 bg-amber-50/40 dark:border-amber-900/60 dark:bg-amber-950/20' : '' }}">
                                                <div class="text-[10px] text-muted-foreground">{{ __('Other / Bill Disc') }}</div>
                                                <div class="font-bold font-mono text-sm mt-0.5 {{ $saleBillDiscount > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground' }}">
                                                    @if($saleBillDiscount > 0)
                                                        -₹ {{ number_format($saleBillDiscount, 2) }}
                                                    @else
                                                        ₹ 0.00
                                                    @endif
                                                </div>
                                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                                    {{ $saleBillDiscount > 0 ? __('Special bill discount') : __('None') }}
                                                </div>
                                            </div>

                                            <!-- Round Off -->
                                            <div class="rounded border border-border bg-card p-2 {{ abs($saleRoundOff) > 0 ? 'border-indigo-200 bg-indigo-50/40 dark:border-indigo-900/60 dark:bg-indigo-950/20' : '' }}">
                                                <div class="text-[10px] text-muted-foreground">{{ __('Round Off') }}</div>
                                                <div class="font-bold font-mono text-sm mt-0.5 {{ abs($saleRoundOff) > 0 ? 'text-indigo-700 dark:text-indigo-400' : 'text-muted-foreground' }}">
                                                    @if(abs($saleRoundOff) > 0)
                                                        {{ $saleRoundOff > 0 ? '+' : '' }}₹ {{ number_format($saleRoundOff, 2) }}
                                                    @else
                                                        ₹ 0.00
                                                    @endif
                                                </div>
                                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                                    {{ __('Coin / cash adjustment') }}
                                                </div>
                                            </div>

                                            <!-- Cash Received (Net Payable) -->
                                            <div class="rounded border border-primary/30 bg-primary/5 p-2">
                                                <div class="text-[10px] font-semibold text-primary">{{ __('Cash Received') }}</div>
                                                <div class="font-bold font-mono text-sm mt-0.5 text-foreground">
                                                    ₹ {{ number_format((float) $sale->total, 2) }}
                                                </div>
                                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                                    {{ __('Via') }} {{ $methodLabel }}
                                                </div>
                                            </div>

                                            <!-- Items Net Cost (PO Cost) -->
                                            <div class="rounded border border-border bg-card p-2">
                                                <div class="text-[10px] text-muted-foreground">{{ __('Items Net Cost') }}</div>
                                                <div class="font-bold font-mono text-sm mt-0.5 text-muted-foreground">
                                                    -₹ {{ number_format($cogs, 2) }}
                                                </div>
                                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                                    {{ __('Vendor PO net cost') }}
                                                </div>
                                            </div>

                                            <!-- Final Gross Profit -->
                                            <div class="rounded border p-2 {{ $profit >= 0 ? 'border-emerald-300 bg-emerald-100/50 dark:border-emerald-800 dark:bg-emerald-950/40' : 'border-red-300 bg-red-100/50 dark:border-red-800 dark:bg-red-950/40' }}">
                                                <div class="text-[10px] font-bold {{ $profit >= 0 ? 'text-emerald-800 dark:text-emerald-300' : 'text-red-800 dark:text-red-300' }}">
                                                    {{ __('Gross Profit') }}
                                                </div>
                                                <div class="font-bold font-mono text-sm mt-0.5 {{ $profit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                    ₹ {{ number_format($profit, 2) }}
                                                </div>
                                                <div class="text-[10px] font-semibold mt-0.5 {{ $profit >= 0 ? 'text-emerald-800 dark:text-emerald-300' : 'text-red-800 dark:text-red-300' }}">
                                                    {{ number_format($margin, 1) }}% {{ __('margin') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="11" class="px-4 py-6 text-center text-muted-foreground text-sm">
                                {{ __('No completed sales in this range.') }}
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        <!-- Invoices Pagination Footer -->
        <div class="px-5 py-3 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground bg-muted/20">
            <div>
                {{ __('Showing') }} <span class="font-semibold text-foreground">{{ $sales->firstItem() ?? 0 }}</span>
                {{ __('to') }} <span class="font-semibold text-foreground">{{ $sales->lastItem() ?? 0 }}</span>
                {{ __('of') }} <span class="font-semibold text-foreground">{{ $sales->total() }}</span> {{ __('invoices') }}
                @if($invoiceSearch || $invoicePaymentMethod !== 'all')
                    <span class="text-primary font-medium">({{ __('filtered from') }} {{ count($allSales) }})</span>
                @endif
            </div>

            @if($sales->hasPages())
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        wire:click="previousPage('invoicesPage')"
                        @disabled($sales->onFirstPage())
                        class="px-2.5 py-1 rounded border border-border bg-card text-foreground disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted text-xs font-medium cursor-pointer"
                    >
                        {{ __('Previous') }}
                    </button>
                    <span class="px-2 font-mono text-xs">
                        {{ $sales->currentPage() }} / {{ $sales->lastPage() }}
                    </span>
                    <button
                        type="button"
                        wire:click="nextPage('invoicesPage')"
                        @disabled(!$sales->hasMorePages())
                        class="px-2.5 py-1 rounded border border-border bg-card text-foreground disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted text-xs font-medium cursor-pointer"
                    >
                        {{ __('Next') }}
                    </button>
                </div>
            @endif
        </div>
    </div>
    </div>
</div>
