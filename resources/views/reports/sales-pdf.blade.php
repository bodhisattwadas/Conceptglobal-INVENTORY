@php
    $storeName = \App\Models\Setting::get('store_name', config('app.name'));
    $storeAddress = \App\Models\Setting::get('store_address', '');
    $storePhone = \App\Models\Setting::get('store_phone', '');
    $storeEmail = \App\Models\Setting::get('store_email', '');
    $section = $section ?? 'full';

    $reportTitle = match($section) {
        'invoices' => 'Completed Invoices Audit Report',
        'brands' => 'Sales by Brand Report',
        'items' => 'Sales by Item Performance Report',
        'daily' => 'Daily Sales Breakdown Report',
        default => 'Sales & Profit Report',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }} ({{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }})</title>
    <style>
        @page {
            margin: 20px 24px 40px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1e293b;
            font-size: 8.5px;
            line-height: 1.3;
        }
        .header-box {
            background-color: #0f172a;
            color: #ffffff;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 14px;
        }
        .header-box h1 {
            font-size: 16px;
            margin: 0 0 4px;
            font-weight: bold;
        }
        .header-box p {
            margin: 0;
            color: #94a3b8;
            font-size: 8.5px;
        }
        .meta-right {
            float: right;
            text-align: right;
            color: #cbd5e1;
            font-size: 8px;
        }
        .clear {
            clear: both;
        }

        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            background-color: #f1f5f9;
            border-left: 3px solid #0284c7;
            padding: 4px 6px;
            margin: 12px 0 6px;
        }

        /* KPI Cards Grid */
        table.kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.kpi-table td.kpi-box {
            width: 16.66%;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            padding: 5px 6px;
            text-align: center;
        }
        .kpi-label {
            font-size: 7px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
        }
        .kpi-value {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
        }
        .kpi-sub {
            font-size: 6.5px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Standard Table with Fixed Layout for High-Performance DomPDF Rendering */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
            page-break-inside: auto;
        }
        table.data-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            font-size: 7.5px;
            overflow: hidden;
            word-wrap: break-word;
        }
        table.data-table th {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            font-size: 7px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .font-mono {
            font-family: monospace;
        }
        .bg-total {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .text-green {
            color: #047857;
        }
        .text-amber {
            color: #b45309;
        }
        .text-red {
            color: #dc2626;
        }
        .text-blue {
            color: #0284c7;
        }

        .footer {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            text-align: center;
            color: #64748b;
            font-size: 7.5px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <div class="footer">
        <strong>{{ $storeName }}</strong>
        @if($storeAddress) | {{ $storeAddress }} @endif
        @if($storePhone) | Phone: {{ $storePhone }} @endif
        @if($storeEmail) | Email: {{ $storeEmail }} @endif
        | Generated on {{ now()->format('d M Y, h:i A') }}
    </div>

    <!-- Header -->
    <div class="header-box">
        <div class="meta-right">
            <div><strong>Report Range:</strong></div>
            <div>{{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</div>
            <div><strong>Generated:</strong> {{ now()->format('d M Y, H:i') }}</div>
        </div>
        <h1>{{ $storeName }} - {{ $reportTitle }}</h1>
        <p>{{ $section === 'invoices' ? 'Completed Invoices, PO Net Purchase Costs & Gross Profit Audit' : ($section === 'brands' ? 'Brand-level Volume, Net Revenue, COGS & Margins' : ($section === 'daily' ? 'Daily Sales, Margin & Payment Mode Distribution' : 'Executive Sales Performance, Profit Margins & Payment Mode Distribution')) }}</p>
        <div class="clear"></div>
    </div>

    <!-- KPI Summary Row -->
    <div class="section-title">Key Performance Indicators (KPIs)</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-box">
                <div class="kpi-label">Total Sales</div>
                <div class="kpi-value" style="color: #0284c7;">Rs. {{ number_format($kpis['net_sales'], 2) }}</div>
                <div class="kpi-sub">Collected</div>
            </td>
            <td class="kpi-box" style="background-color: #ecfdf5;">
                <div class="kpi-label" style="color: #065f46;">Gross Profit</div>
                <div class="kpi-value" style="color: #047857;">Rs. {{ number_format($kpis['gross_profit'], 2) }}</div>
                <div class="kpi-sub" style="color: #047857;">{{ $kpis['margin'] }}% (COGS: Rs. {{ number_format($kpis['cogs'], 2) }})</div>
            </td>
            <td class="kpi-box">
                <div class="kpi-label">Gross Sales</div>
                <div class="kpi-value">Rs. {{ number_format($kpis['gross_sales'], 2) }}</div>
                <div class="kpi-sub">Pre-discounts</div>
            </td>
            <td class="kpi-box">
                <div class="kpi-label">Discounts</div>
                <div class="kpi-value" style="color: #d97706;">Rs. {{ number_format($kpis['total_discounts'], 2) }}</div>
                <div class="kpi-sub">Item + Coupons</div>
            </td>
            <td class="kpi-box">
                <div class="kpi-label">Orders</div>
                <div class="kpi-value">{{ number_format($kpis['total_orders']) }}</div>
                <div class="kpi-sub">Completed</div>
            </td>
            <td class="kpi-box">
                <div class="kpi-label">Avg Order Value</div>
                <div class="kpi-value">Rs. {{ number_format($kpis['average_order_value'], 2) }}</div>
                <div class="kpi-sub">Per Invoice</div>
            </td>
        </tr>
    </table>

    @if($section === 'full' || $section === 'daily')
        <!-- Payment Methods Breakdown -->
        <div class="section-title">Sales by Payment Method</div>
        <table class="data-table">
            <colgroup>
                <col style="width: 35%;">
                <col style="width: 20%;">
                <col style="width: 25%;">
                <col style="width: 20%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Payment Mode</th>
                    <th class="text-center">Transaction Count</th>
                    <th class="text-right">Total Amount (Rs.)</th>
                    <th class="text-right">% Share of Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paymentMethods as $mode)
                    <tr>
                        <td class="font-bold">{{ $mode['label'] }}</td>
                        <td class="text-center">{{ $mode['count'] }}</td>
                        <td class="text-right">Rs. {{ number_format($mode['amount'], 2) }}</td>
                        <td class="text-right font-bold">{{ $mode['percentage'] }} %</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-total">
                    <td>Total Net Revenue</td>
                    <td class="text-center">{{ $kpis['total_orders'] }}</td>
                    <td class="text-right">Rs. {{ number_format($kpis['net_sales'], 2) }}</td>
                    <td class="text-right">100.0 %</td>
                </tr>
            </tfoot>
        </table>

        <!-- Daily Summary Table -->
        <div class="section-title">Daily Sales, Profit & Collections Summary</div>
        <table class="data-table">
            <colgroup>
                <col style="width: 14%;">
                <col style="width: 7%;">
                <col style="width: 11%;">
                <col style="width: 11%;">
                <col style="width: 12%;">
                <col style="width: 15%;">
                <col style="width: 7.5%;">
                <col style="width: 7.5%;">
                <col style="width: 7.5%;">
                <col style="width: 7.5%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-center">Orders</th>
                    <th class="text-right">Gross Sales</th>
                    <th class="text-right">Discounts</th>
                    <th class="text-right font-bold">Net Total</th>
                    <th class="text-right font-bold text-green">Gross Profit</th>
                    <th class="text-right">UPI</th>
                    <th class="text-right">Card</th>
                    <th class="text-right">Cash</th>
                    <th class="text-right">Transfer</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dailyBreakdown as $row)
                    <tr>
                        <td>{{ $row['formatted_date'] }}</td>
                        <td class="text-center">{{ $row['orders_count'] }}</td>
                        <td class="text-right">Rs. {{ number_format($row['gross_sales'], 2) }}</td>
                        <td class="text-right text-amber">{{ $row['total_discounts'] > 0 ? '-Rs. ' . number_format($row['total_discounts'], 2) : 'Rs. 0.00' }}</td>
                        <td class="text-right font-bold">Rs. {{ number_format($row['net_sales'], 2) }}</td>
                        <td class="text-right font-bold text-green">
                            Rs. {{ number_format($row['gross_profit'], 2) }} ({{ $row['margin'] }}%)
                        </td>
                        <td class="text-right">Rs. {{ number_format($row['upi'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($row['card'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($row['cash'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($row['transfer'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center">No completed sales recorded in this period.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($dailyBreakdown->isNotEmpty())
                <tfoot>
                    <tr class="bg-total">
                        <td>TOTAL</td>
                        <td class="text-center">{{ $kpis['total_orders'] }}</td>
                        <td class="text-right">Rs. {{ number_format($kpis['gross_sales'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($kpis['total_discounts'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($kpis['net_sales'], 2) }}</td>
                        <td class="text-right text-green">
                            Rs. {{ number_format($kpis['gross_profit'], 2) }} ({{ $kpis['margin'] }}%)
                        </td>
                        <td class="text-right">Rs. {{ number_format($paymentMethods['upi']['amount'] ?? 0, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($paymentMethods['card']['amount'] ?? 0, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($paymentMethods['cash']['amount'] ?? 0, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($paymentMethods['transfer']['amount'] ?? 0, 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif

    @if($section === 'full' || $section === 'brands')
        <!-- Sales by Brand Summary Table -->
        @if(isset($brandBreakdown) && $brandBreakdown->isNotEmpty())
            <div class="section-title">Sales & Profit by Brand Summary</div>
            <table class="data-table">
                <colgroup>
                    <col style="width: 22%;">
                    <col style="width: 7%;">
                    <col style="width: 9%;">
                    <col style="width: 11%;">
                    <col style="width: 10%;">
                    <col style="width: 11%;">
                    <col style="width: 7%;">
                    <col style="width: 10%;">
                    <col style="width: 8%;">
                    <col style="width: 5%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Brand / Category</th>
                        <th class="text-center">Items</th>
                        <th class="text-center">Units Sold</th>
                        <th class="text-right">Gross Sales</th>
                        <th class="text-right">Discounts</th>
                        <th class="text-right font-bold">Net Sales</th>
                        <th class="text-center">Share</th>
                        <th class="text-right">PO Net Cost</th>
                        <th class="text-right font-bold text-green">Gross Profit</th>
                        <th class="text-center">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($brandBreakdown as $b)
                        <tr>
                            <td class="font-bold">{{ $b['name'] }}</td>
                            <td class="text-center">{{ $b['items_count'] }}</td>
                            <td class="text-center font-bold">{{ number_format($b['total_quantity']) }}</td>
                            <td class="text-right">Rs. {{ number_format($b['gross_sales'], 2) }}</td>
                            <td class="text-right text-amber">{{ $b['total_discounts'] > 0 ? '-Rs. ' . number_format($b['total_discounts'], 2) : 'Rs. 0.00' }}</td>
                            <td class="text-right font-bold">Rs. {{ number_format($b['net_sales'], 2) }}</td>
                            <td class="text-center">{{ $b['percentage'] }}%</td>
                            <td class="text-right">Rs. {{ number_format($b['cogs'], 2) }}</td>
                            <td class="text-right font-bold text-green">Rs. {{ number_format($b['gross_profit'], 2) }}</td>
                            <td class="text-center font-bold">{{ $b['margin'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($section === 'brands')
                <!-- Sales by Item by Brand Breakdown Table (Included in Brand Section PDF) -->
                <div class="section-title">Sales by Item by Brand Breakdown</div>
                <table class="data-table">
                    <colgroup>
                        <col style="width: 14%;">
                        <col style="width: 20%;">
                        <col style="width: 12%;">
                        <col style="width: 7%;">
                        <col style="width: 9%;">
                        <col style="width: 8%;">
                        <col style="width: 10%;">
                        <col style="width: 8%;">
                        <col style="width: 8%;">
                        <col style="width: 4%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Brand</th>
                            <th>Product / Service Name</th>
                            <th>Code / SKU</th>
                            <th class="text-center">Qty Sold</th>
                            <th class="text-right">Unit MRP</th>
                            <th class="text-right">Discounts</th>
                            <th class="text-right font-bold">Net Sales</th>
                            <th class="text-right">PO Net Cost</th>
                            <th class="text-right font-bold text-green">Gross Profit</th>
                            <th class="text-center">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($brandBreakdown as $b)
                            @foreach($b['items'] as $item)
                                <tr>
                                    <td class="font-bold">{{ $b['name'] }}</td>
                                    <td>{{ $item['name'] }}</td>
                                    <td class="font-mono text-center">{{ $item['code'] }}</td>
                                    <td class="text-center font-bold">{{ number_format($item['quantity']) }}</td>
                                    <td class="text-right">Rs. {{ number_format($item['unit_price'], 2) }}</td>
                                    <td class="text-right text-amber">{{ $item['total_discounts'] > 0 ? '-Rs. ' . number_format($item['total_discounts'], 2) : 'Rs. 0.00' }}</td>
                                    <td class="text-right font-bold">Rs. {{ number_format($item['net_sales'], 2) }}</td>
                                    <td class="text-right">Rs. {{ number_format($item['cogs'], 2) }}</td>
                                    <td class="text-right font-bold text-green">Rs. {{ number_format($item['gross_profit'], 2) }}</td>
                                    <td class="text-center font-bold">{{ $item['margin'] }}%</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    @endif

    @if(($section === 'full' || $section === 'items') && isset($itemBreakdown) && $itemBreakdown->isNotEmpty())
        <!-- Sales by Item Performance Table -->
        <div class="section-title">Sales by Item Performance & Profit Audit</div>
        <table class="data-table">
            <colgroup>
                <col style="width: 24%;">
                <col style="width: 13%;">
                <col style="width: 14%;">
                <col style="width: 6%;">
                <col style="width: 6%;">
                <col style="width: 8%;">
                <col style="width: 7%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
                <col style="width: 6%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Code / SKU</th>
                    <th>Brand / Category</th>
                    <th class="text-center">Type</th>
                    <th class="text-center">Qty Sold</th>
                    <th class="text-right">Unit MRP</th>
                    <th class="text-right">Discounts</th>
                    <th class="text-right font-bold">Net Sales</th>
                    <th class="text-right">PO Net Cost</th>
                    <th class="text-right font-bold text-green">Gross Profit</th>
                    <th class="text-center">Margin</th>
                </tr>
            </thead>
            <tbody>
                @foreach($itemBreakdown as $item)
                    <tr>
                        <td class="font-bold">{{ $item['name'] }}</td>
                        <td class="font-mono text-center">{{ $item['code'] }}</td>
                        <td>{{ $item['brand_name'] }}</td>
                        <td class="text-center">{{ $item['is_service'] ? 'Service' : 'Product' }}</td>
                        <td class="text-center font-bold">{{ number_format($item['quantity']) }}</td>
                        <td class="text-right">Rs. {{ number_format($item['unit_price'], 2) }}</td>
                        <td class="text-right text-amber">{{ $item['total_discounts'] > 0 ? '-Rs. ' . number_format($item['total_discounts'], 2) : 'Rs. 0.00' }}</td>
                        <td class="text-right font-bold">Rs. {{ number_format($item['net_sales'], 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($item['cogs'], 2) }}</td>
                        <td class="text-right font-bold text-green">Rs. {{ number_format($item['gross_profit'], 2) }}</td>
                        <td class="text-center font-bold">{{ $item['margin'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($section === 'full' || $section === 'invoices')
        <!-- Completed Invoices Audit Table -->
        <div class="section-title">Completed Invoices & Items Cost Audit</div>
        <table class="data-table">
            <colgroup>
                <col style="width: 14%;">
                <col style="width: 13%;">
                <col style="width: 15%;">
                <col style="width: 7%;">
                <col style="width: 9%;">
                <col style="width: 8%;">
                <col style="width: 10%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 6%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th class="text-center">Mode</th>
                    <th class="text-right">Gross</th>
                    <th class="text-right">Discount</th>
                    <th class="text-right font-bold">Cash Recv.</th>
                    <th class="text-right">Items Net Cost</th>
                    <th class="text-right font-bold text-green">Gross Profit</th>
                    <th class="text-center">Margin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                    @php
                        $methodLabel = $sale->payment_method instanceof \App\Enums\PaymentMethod
                            ? $sale->payment_method->label()
                            : ucfirst((string) $sale->payment_method);
                        $profit = (float) ($sale->gross_profit ?? 0);
                        $margin = (float) ($sale->margin ?? 0);
                        $cogs = (float) ($sale->cogs ?? 0);
                        $discount = (float) ($sale->total_discount ?? 0);
                    @endphp
                    <tr>
                        <td class="font-bold font-mono">{{ $sale->invoice_number }}</td>
                        <td>{{ $sale->sale_date ? $sale->sale_date->format('d M Y, H:i') : '-' }}</td>
                        <td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td>
                        <td class="text-center">{{ $methodLabel }}</td>
                        <td class="text-right">Rs. {{ number_format((float) $sale->subtotal, 2) }}</td>
                        <td class="text-right text-amber">
                            {{ $discount > 0 ? '-Rs. ' . number_format($discount, 2) : 'Rs. 0.00' }}
                        </td>
                        <td class="text-right font-bold">Rs. {{ number_format((float) $sale->total, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($cogs, 2) }}</td>
                        <td class="text-right font-bold {{ $profit >= 0 ? 'text-green' : 'text-red' }}">
                            Rs. {{ number_format($profit, 2) }}
                        </td>
                        <td class="text-center font-bold">{{ number_format($margin, 1) }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center">No invoices found in this period.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($sales->isNotEmpty())
                <tfoot>
                    @php
                        $totGross = (float) $sales->sum(fn($s) => (float)$s->subtotal);
                        $totDisc = (float) $sales->sum(fn($s) => (float)($s->total_discount ?? 0));
                        $totNet = (float) $sales->sum(fn($s) => (float)$s->total);
                        $totCost = (float) $sales->sum(fn($s) => (float)($s->cogs ?? 0));
                        $totProfit = (float) $sales->sum(fn($s) => (float)($s->gross_profit ?? 0));
                        $totMargin = $totNet > 0 ? round(($totProfit / $totNet) * 100, 1) : 0.0;
                    @endphp
                    <tr class="bg-total">
                        <td>TOTAL</td>
                        <td colspan="3" class="text-center">{{ count($sales) }} Invoices</td>
                        <td class="text-right">Rs. {{ number_format($totGross, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($totDisc, 2) }}</td>
                        <td class="text-right font-bold">Rs. {{ number_format($totNet, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($totCost, 2) }}</td>
                        <td class="text-right font-bold text-green">Rs. {{ number_format($totProfit, 2) }}</td>
                        <td class="text-center font-bold">{{ $totMargin }}%</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif
</body>
</html>
