<div>
    <div class="space-y-6" wire:poll.60s="refreshDashboard">
        <!-- Filter Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-card p-4 rounded-lg border border-border shadow-sm">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Overview</h2>
            <p class="text-sm text-muted-foreground">Monitor your business performance at a glance.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <!-- Period Selector -->
            <select wire:model.live="dateFilter" class="h-9 w-[180px] rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                @foreach(\App\Enums\DatePeriod::cases() as $period)
                    <option value="{{ $period->value }}">{{ $period->label() }}</option>
                @endforeach
            </select>

            <!-- Custom Date Range -->
            <!-- Custom Date Range (Flatpickr) -->
            <div x-show="$wire.dateFilter === 'custom'" x-transition class="flex items-center gap-2"
                 x-data="{
                     init() {
                         flatpickr(this.$refs.picker, {
                             mode: 'range',
                             dateFormat: 'Y-m-d',
                             defaultDate: [this.$wire.customStartDate, this.$wire.customEndDate],
                             onChange: (selectedDates, dateStr, instance) => {
                                 if (selectedDates.length === 2) {
                                     this.$wire.updateCustomRange(
                                         instance.formatDate(selectedDates[0], 'Y-m-d'),
                                         instance.formatDate(selectedDates[1], 'Y-m-d')
                                     );
                                 }
                             }
                         });
                     }
                 }"
            >
                <input x-ref="picker" type="text" class="h-9 w-[240px] rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" placeholder="Select date range...">
            </div>

             <!-- Refresh Button -->
             <button wire:click="refreshDashboard" class="print:hidden inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 w-9">
                <x-heroicon-o-arrow-path wire:loading.class="animate-spin" wire:target="refreshDashboard, dateFilter, updateCustomRange" class="h-4 w-4" />
            </button>
            
            <!-- Print Button -->
            <button onclick="window.print()" class="print:hidden inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-9 px-4 py-2 gap-2">
                <x-heroicon-o-printer class="h-4 w-4" />
                <span class="hidden sm:inline">Print Report</span>
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <!-- Total Sales -->
        <button type="button" class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" style="background-color: #eff6ff; border-color: #bfdbfe; color: #172554;" onclick="document.getElementById('dashboard-sales-trend')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Total Sales</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #dbeafe; color: #1d4ed8;">
                    <x-heroicon-o-banknotes class="h-4 w-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">
                    @money($stats['total_sales'] ?? 0)
                </div>
                <p class="text-xs mt-1" style="color: rgba(29, 78, 216, 0.8);">
                    {{ $stats['sales_count'] ?? 0 }} transactions
                </p>
            </div>
        </button>

        <!-- Gross Profit -->
        <button type="button" class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" style="background-color: #ecfdf5; border-color: #a7f3d0; color: #064e3b;" onclick="document.getElementById('dashboard-sales-trend')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Gross Profit</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #d1fae5; color: #047857;">
                    <x-heroicon-o-arrow-trending-up class="h-4 w-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">
                    @money($stats['gross_profit'] ?? 0)
                </div>
                <p class="text-xs mt-1" style="color: rgba(4, 120, 87, 0.8);">
                    Estimated based on COGS
                </p>
            </div>
        </button>

        <!-- Net Cash Flow -->
        <button type="button" class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2" style="background-color: #f5f3ff; border-color: #ddd6fe; color: #2e1065;" onclick="document.getElementById('dashboard-cash-flow')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Net Cash Flow</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #ede9fe; color: #6d28d9;">
                    <x-heroicon-o-currency-dollar class="h-4 w-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold {{ ($stats['net_cash_flow'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    @money($stats['net_cash_flow'] ?? 0)
                </div>
                <div class="flex justify-between text-[11px] sm:text-xs mt-1">
                    <span class="text-emerald-600 flex items-center gap-1" title="Total Income">
                        <x-heroicon-s-arrow-up class="w-3 h-3" /> @money($stats['income'] ?? 0)
                    </span>
                    <span class="text-red-600 flex items-center gap-1" title="Total Expense">
                        <x-heroicon-s-arrow-down class="w-3 h-3" /> @money($stats['expense'] ?? 0)
                    </span>
                </div>
            </div>
        </button>

         <!-- Low Stock Alert -->
         <button type="button" class="rounded-xl border shadow-sm text-left transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2" style="background-color: #fff1f2; border-color: #fecdd3; color: #4c0519;" onclick="document.getElementById('dashboard-low-stock-products')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <div class="p-4 flex flex-row items-center justify-between space-y-0 pb-2">
                <h3 class="tracking-tight text-sm font-medium">Low Stock Alert</h3>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: #ffe4e6; color: #be123c;">
                    <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
                </span>
            </div>
            <div class="p-4 pt-0">
                <div class="text-xl sm:text-2xl font-bold">
                    {{ count($lowStockProducts) }}
                </div>
                <p class="text-xs mt-1" style="color: rgba(190, 18, 60, 0.8);">
                    Items below min stock
                </p>
            </div>
        </button>
    </div>

    <!-- Charts Section -->
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <!-- Sales Trend -->
        <div id="dashboard-sales-trend" class="scroll-mt-24 md:col-span-2 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 pb-2">
                <h3 class="font-semibold leading-none tracking-tight">Sales Trend</h3>
                <p class="text-xs text-muted-foreground">Daily sales performance.</p>
            </div>
            <div class="p-4 pt-0" wire:ignore>
                <div id="salesChart" class="w-full h-[250px]"></div>
            </div>
        </div>

        <!-- Cash Flow -->
        <div id="dashboard-cash-flow" class="scroll-mt-24 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 pb-2">
                <h3 class="font-semibold leading-none tracking-tight">Income vs Expense</h3>
                <p class="text-xs text-muted-foreground">Financial overview.</p>
            </div>
            <div class="p-4 pt-0" wire:ignore>
                <div id="cashFlowChart" class="w-full h-[250px]"></div>
            </div>
        </div>

        <!-- Expense Breakdown -->
        <div class="rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 pb-2">
                <h3 class="font-semibold leading-none tracking-tight">Expense Breakdown</h3>
                <p class="text-xs text-muted-foreground">Category distribution.</p>
            </div>
            <div class="p-4 pt-0" wire:ignore>
                <div id="expenseChart" class="w-full h-[250px] flex items-center justify-center"></div>
            </div>
        </div>
    </div>

    <!-- Data Tables Section -->
    <div class="grid gap-4 md:grid-cols-2">
        <!-- Recent Sales -->
        <div id="dashboard-low-stock-products" class="scroll-mt-24 col-span-1 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 border-b">
                <h3 class="font-semibold leading-none tracking-tight">Recent Sales</h3>
                <p class="text-xs text-muted-foreground">Latest transactions overview.</p>
            </div>
            <div class="p-0">
                <div class="relative w-full overflow-auto max-h-[300px]">
                    <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b sticky top-0 bg-card z-10">
                            <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Invoice</th>
                                <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0 bg-transparent">
                            @forelse($recentSales as $sale)
                                <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                    <td class="px-4 py-2 align-middle font-medium">
                                        {{ $sale['invoice_number'] }}
                                        <div class="text-[11px] text-muted-foreground font-normal">{{ $sale['customer']['name'] ?? 'Guest' }}</div>
                                    </td>
                                    <td class="px-4 py-2 align-middle text-right font-medium text-emerald-600">@money($sale['total'])</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="p-4 text-center text-muted-foreground">No recent sales.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Low Stock Products -->
        <div class="col-span-1 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 border-b">
                <h3 class="font-semibold leading-none tracking-tight">Low Stock Products</h3>
                <p class="text-xs text-muted-foreground">All active products at or below minimum quantity.</p>
            </div>
            <div class="p-0">
                <div class="relative w-full overflow-auto max-h-[300px]">
                    <table class="w-full caption-bottom text-sm">
                        <thead class="[&_tr]:border-b sticky top-0 bg-card z-10">
                            <tr class="border-b transition-colors hover:bg-muted/50">
                                <th class="h-10 px-4 text-left align-middle font-medium text-muted-foreground">Product</th>
                                <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Qty</th>
                                <th class="h-10 px-4 text-right align-middle font-medium text-muted-foreground">Min</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0 bg-transparent">
                            @forelse($lowStockProducts as $product)
                                <tr class="border-b transition-colors hover:bg-muted/50">
                                    <td class="px-4 py-2 align-middle font-medium">
                                        <div class="truncate max-w-[220px]" title="{{ $product['name'] }}">{{ $product['name'] }}</div>
                                        <div class="text-[11px] text-muted-foreground font-normal">{{ $product['sku'] }}</div>
                                    </td>
                                    <td class="px-4 py-2 align-middle text-right font-semibold text-red-600">{{ number_format($product['quantity']) }}</td>
                                    <td class="px-4 py-2 align-middle text-right text-muted-foreground">{{ number_format($product['min_stock']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="p-4 text-center text-muted-foreground">No low stock products.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-2">
        <!-- Top Selling Products -->
        <div class="col-span-1 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 border-b">
                <h3 class="font-semibold leading-none tracking-tight">Top Products</h3>
                <p class="text-xs text-muted-foreground">Best selling items.</p>
            </div>
             <div class="p-4 pt-4 max-h-[300px] overflow-auto">
                <div class="space-y-4">
                    @forelse($topProducts as $product)
                        <div class="flex items-center justify-between">
                            <div class="space-y-1 flex-1">
                                <p class="text-sm font-medium leading-none truncate pr-2" title="{{ $product['product_name'] }}">{{ $product['product_name'] }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ $product['sku'] }}</p>
                            </div>
                            <div class="font-semibold text-sm bg-muted px-2 py-1 rounded-md">
                                {{ $product['total_sold'] }} <span class="text-xs font-normal text-muted-foreground">sold</span>
                            </div>
                        </div>
                    @empty
                         <p class="text-xs text-muted-foreground text-center py-2">No product data.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Top Customers -->
        <div class="col-span-1 rounded-xl border bg-card text-card-foreground shadow-sm break-inside-avoid">
            <div class="p-4 flex flex-col space-y-1.5 border-b">
                <h3 class="font-semibold leading-none tracking-tight">Top Customers</h3>
                <p class="text-xs text-muted-foreground">By highest revenue.</p>
            </div>
             <div class="p-4 pt-4 max-h-[300px] overflow-auto">
                <div class="space-y-4">
                    @forelse($topCustomers as $customer)
                        <div class="flex items-center justify-between">
                            <div class="space-y-1 flex-1">
                                <p class="text-sm font-medium leading-none truncate pr-2" title="{{ $customer['customer_name'] }}">{{ $customer['customer_name'] }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ $customer['phone'] }}</p>
                            </div>
                            <div class="font-semibold text-sm text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md whitespace-nowrap">
                                @money($customer['total_spent'])
                            </div>
                        </div>
                    @empty
                         <p class="text-xs text-muted-foreground text-center py-2">No customer data.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        @page { size: landscape; margin: 1cm; }
        body { background-color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print\:hidden { display: none !important; }
        .bg-card { border: 1px solid #e2e8f0; box-shadow: none !important; }
        .grid { gap: 1rem !important; }
        /* Prevent charts and cards from breaking across pages */
        .break-inside-avoid { break-inside: avoid; page-break-inside: avoid; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('livewire:initialized', () => {
        let salesChart = null;
        let cashFlowChart = null;
        
        const currencySymbol = "{{ \App\Models\Setting::get('currency_symbol', 'Rp') }}";
        const currencyPosition = "{{ \App\Models\Setting::get('currency_position', 'left') }}";

        const formatMoney = (val) => {
            let num = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(val);
            return currencyPosition === 'left' ? currencySymbol + ' ' + num : num + ' ' + currencySymbol;
        };

        const initCharts = (data) => {
            // Sales Chart
            const salesOptions = {
                series: [{
                    name: 'Sales',
                    data: data.sales.data
                }],
                chart: {
                    type: 'area',
                    height: 250,
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                    parentHeightOffset: 0
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: {
                    categories: data.sales.labels,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: { cssClass: 'text-[10px] text-muted-foreground' }
                    }
                },
                yaxis: {
                    labels: {
                        style: { cssClass: 'text-[10px] text-muted-foreground' }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                             return formatMoney(val);
                        }
                    }
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.7,
                        opacityTo: 0.2,
                        stops: [0, 90, 100]
                    }
                },
                colors: ['#0ea5e9'], // Sky 500
                tooltip: {
                    y: {
                        formatter: function (val) {
                             return formatMoney(val);
                        }
                    }
                }
            };

            // Cash Flow Chart
            const cashFlowOptions = {
                series: [{
                    name: 'Income',
                    data: data.cashFlow.income
                }, {
                    name: 'Expense',
                    data: data.cashFlow.expense
                }],
                chart: {
                    type: 'bar',
                    height: 250,
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                    parentHeightOffset: 0
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        endingShape: 'rounded'
                    },
                },
                dataLabels: { enabled: false },
                stroke: { show: true, width: 2, colors: ['transparent'] },
                xaxis: {
                    categories: data.cashFlow.labels,
                    labels: {
                        style: { cssClass: 'text-[10px] text-muted-foreground' }
                    }
                },
                yaxis: {
                    labels: {
                        style: { cssClass: 'text-[10px] text-muted-foreground' },
                        formatter: (val) => {
                             // Shorten detailed numbers for y-axis
                             if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
                             if (val >= 1000) return (val / 1000).toFixed(0) + 'k';
                             return val;
                        }
                    }
                },
                colors: ['#10b981', '#ef4444'], // Emerald 500, Red 500
                fill: { opacity: 1 },
                tooltip: {
                    y: {
                        formatter: function (val) {
                             return formatMoney(val);
                        }
                    }
                }
            };

            // Expense Breakdown Chart
            const hasExpenseData = data.expense.series && data.expense.series.length > 0;
            const expenseOptions = {
                series: hasExpenseData ? data.expense.series.map(Number) : [1],
                labels: hasExpenseData ? data.expense.labels : ['No Data'],
                chart: {
                    type: 'donut',
                    height: 250,
                    fontFamily: 'inherit',
                    parentHeightOffset: 0
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%'
                        }
                    }
                },
                dataLabels: { enabled: false },
                colors: hasExpenseData ? ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#06b6d4', '#6366f1'] : ['#e5e7eb'],
                tooltip: {
                    enabled: hasExpenseData,
                    y: {
                        formatter: function (val) {
                             return formatMoney(val);
                        }
                    }
                },
                legend: {
                    position: 'bottom',
                    offsetY: 0,
                    height: 60,
                }
            };

            if (salesChart) {
                salesChart.updateOptions(salesOptions, true, true);
            } else {
                salesChart = new ApexCharts(document.querySelector("#salesChart"), salesOptions);
                salesChart.render();
            }

            if (cashFlowChart) {
                cashFlowChart.updateOptions(cashFlowOptions, true, true);
            } else {
                cashFlowChart = new ApexCharts(document.querySelector("#cashFlowChart"), cashFlowOptions);
                cashFlowChart.render();
            }

            if (window.expenseChartInst) {
                window.expenseChartInst.updateOptions(expenseOptions, true, true);
            } else {
                window.expenseChartInst = new ApexCharts(document.querySelector("#expenseChart"), expenseOptions);
                window.expenseChartInst.render();
            }
        };

        // Initial Load
        initCharts({
            sales: @json($salesChart),
            cashFlow: @json($cashFlowChart),
            expense: @json($expenseChart)
        });



        // Listen for server-side updates
        Livewire.on('stats-updated', (data) => {
             initCharts(data[0]); // data is array of args
        });
    });
</script>
</div>
