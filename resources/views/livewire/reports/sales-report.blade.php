<div class="space-y-6">
    <!-- Filter and Action Bar -->
    <div class="bg-card border border-border rounded-xl p-4 sm:p-5 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Date Filter Presets & Inputs -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label for="dateFilter" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ __('Period') }}:
                    </label>
                    <select
                        id="dateFilter"
                        wire:model.live="dateFilter"
                        class="rounded-lg border-input bg-background text-sm font-medium text-foreground py-1.5 px-3 shadow-sm focus:border-primary focus:ring-primary"
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

                <!-- Custom Range Inputs -->
                <div class="flex items-center gap-2">
                    <span class="text-xs text-muted-foreground">{{ __('From') }}:</span>
                    <input
                        type="date"
                        wire:model.live="startDate"
                        class="rounded-lg border-input bg-background text-sm text-foreground py-1.5 px-2.5 shadow-sm focus:border-primary focus:ring-primary"
                    />
                    <span class="text-xs text-muted-foreground">{{ __('To') }}:</span>
                    <input
                        type="date"
                        wire:model.live="endDate"
                        class="rounded-lg border-input bg-background text-sm text-foreground py-1.5 px-2.5 shadow-sm focus:border-primary focus:ring-primary"
                    />
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-secondary text-secondary-foreground hover:bg-secondary/80 px-3.5 py-2 text-sm font-medium shadow-sm transition-colors disabled:opacity-50"
                >
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    <span wire:loading.remove wire:target="exportCsv">{{ __('Export CSV') }}</span>
                    <span wire:loading wire:target="exportCsv">{{ __('Exporting...') }}</span>
                </button>

                <a
                    href="{{ route('reports.sales.pdf', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 px-3.5 py-2 text-sm font-medium shadow-sm transition-colors"
                >
                    <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                    <span>{{ __('Download PDF') }}</span>
                </a>

                <a
                    href="{{ route('reports.sales.pdf', ['start_date' => $startDate, 'end_date' => $endDate, 'stream' => 1]) }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg border border-border bg-background hover:bg-accent hover:text-accent-foreground px-3.5 py-2 text-sm font-medium shadow-sm transition-colors"
                >
                    <x-heroicon-o-printer class="w-4 h-4" />
                    <span>{{ __('Print View') }}</span>
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
                {{ $kpis['total_orders'] }} {{ __('Completed Transactions') }}
            </span>
        </div>
    </div>

    <!-- Executive KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Net Revenue -->
        <div class="bg-card border border-border rounded-xl p-4 sm:p-5 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('Net Revenue') }}</span>
                <span class="p-2 rounded-lg bg-green-500/10 text-green-600 dark:text-green-400">
                    <x-heroicon-o-currency-rupee class="w-5 h-5" />
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold text-foreground">
                    ₹ {{ number_format($kpis['net_sales'], 2) }}
                </div>
                <div class="text-xs text-muted-foreground mt-1">
                    {{ __('Actual collected revenue') }}
                </div>
            </div>
        </div>

        <!-- Gross Sales -->
        <div class="bg-card border border-border rounded-xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('Gross Sales') }}</span>
                <span class="p-2 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <x-heroicon-o-shopping-bag class="w-5 h-5" />
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold text-foreground">
                    ₹ {{ number_format($kpis['gross_sales'], 2) }}
                </div>
                <div class="text-xs text-muted-foreground mt-1">
                    {{ __('Before discounts & coupons') }}
                </div>
            </div>
        </div>

        <!-- Total Discounts -->
        <div class="bg-card border border-border rounded-xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('Total Discounts') }}</span>
                <span class="p-2 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <x-heroicon-o-tag class="w-5 h-5" />
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                    ₹ {{ number_format($kpis['total_discounts'], 2) }}
                </div>
                <div class="text-xs text-muted-foreground mt-1">
                    {{ __('Items + coupons + bill discounts') }}
                </div>
            </div>
        </div>

        <!-- Orders Count -->
        <div class="bg-card border border-border rounded-xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('Total Orders') }}</span>
                <span class="p-2 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400">
                    <x-heroicon-o-receipt-percent class="w-5 h-5" />
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold text-foreground">
                    {{ number_format($kpis['total_orders']) }}
                </div>
                <div class="text-xs text-muted-foreground mt-1">
                    {{ __('Completed sales transactions') }}
                </div>
            </div>
        </div>

        <!-- Average Order Value -->
        <div class="bg-card border border-border rounded-xl p-4 sm:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('Average Order Value') }}</span>
                <span class="p-2 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                    <x-heroicon-o-chart-pie class="w-5 h-5" />
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold text-foreground">
                    ₹ {{ number_format($kpis['average_order_value'], 2) }}
                </div>
                <div class="text-xs text-muted-foreground mt-1">
                    {{ __('Net revenue / total orders') }}
                </div>
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
                            <div class="text-xs text-muted-foreground">{{ $upi['count'] }} {{ __('orders') }}</div>
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
                            <div class="text-xs text-muted-foreground">{{ $card['count'] }} {{ __('orders') }}</div>
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
                            <div class="text-xs text-muted-foreground">{{ $cash['count'] }} {{ __('orders') }}</div>
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
                            <div class="text-xs text-muted-foreground">{{ $transfer['count'] }} {{ __('orders') }}</div>
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

    <!-- Daily Sales & Payment Breakdown Table -->
    <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-border flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-table-cells class="w-5 h-5 text-primary" />
                    <span>{{ __('Daily Sales & Collections Summary') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('Day-by-day revenue, invoice counts, and payment mode breakdowns.') }}
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
                        <th class="px-4 py-3 text-center">{{ __('Invoices') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Gross Sales') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Discounts') }}</th>
                        <th class="px-4 py-3 text-right font-bold text-foreground">{{ __('Net Total') }}</th>
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
                            <td colspan="9" class="px-4 py-8 text-center text-muted-foreground">
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

    <!-- Recent Sales Invoices List in Range -->
    <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-border flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-receipt-percent class="w-5 h-5 text-primary" />
                    <span>{{ __('Completed Invoices in Period') }}</span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ __('List of completed transactions with customer details and payment methods.') }}
                </p>
            </div>
            <span class="text-xs font-medium text-muted-foreground">
                {{ count($sales) }} {{ __('Invoices') }}
            </span>
        </div>

        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-left text-sm text-foreground">
                <thead class="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground sticky top-0 border-b border-border">
                    <tr>
                        <th class="px-4 py-3">{{ __('Invoice #') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3">{{ __('Payment Mode') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Discount') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Net Amount') }}</th>
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
                        @endphp
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 font-semibold text-primary whitespace-nowrap">
                                <a href="{{ route('sales.show', $sale) }}" class="hover:underline">
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
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $badgeClass }}">
                                    {{ $methodLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-amber-600 dark:text-amber-400">
                                ₹ {{ number_format((float) ($sale->total_discount ?? 0) + (float) ($sale->coupon_discount ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-bold text-foreground">
                                ₹ {{ number_format((float) $sale->total, 2) }}
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
                            <td colspan="7" class="px-4 py-6 text-center text-muted-foreground text-sm">
                                {{ __('No completed sales in this range.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
