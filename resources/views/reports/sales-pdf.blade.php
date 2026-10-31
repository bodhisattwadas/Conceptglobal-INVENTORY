@php
    $storeName = \App\Models\Setting::get('store_name', config('app.name'));
    $storeAddress = \App\Models\Setting::get('store_address', '');
    $storePhone = \App\Models\Setting::get('store_phone', '');
    $storeEmail = \App\Models\Setting::get('store_email', '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report ({{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }})</title>
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

        /* Standard Table */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            font-size: 7.5px;
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
        .bg-total {
            background-color: #f1f5f9;
            font-weight: bold;
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
        <h1>{{ $storeName }} - Sales & Profit Report</h1>
        <p>Executive Sales Performance, Profit Margins & Payment Mode Distribution (UPI, Card, Cash, Transfer)</p>
        <div class="clear"></div>
    </div>

    <!-- KPI Summary Row -->
    <div class="section-title">Key Performance Indicators (KPIs)</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-box">
                <div class="kpi-label">Net Sales</div>
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

    <!-- Payment Methods Breakdown -->
    <div class="section-title">Sales by Payment Method</div>
    <table class="data-table">
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
        <thead>
            <tr>
                <th>Date</th>
                <th class="text-center">Orders</th>
                <th class="text-right">Gross Sales</th>
                <th class="text-right">Discounts</th>
                <th class="text-right font-bold">Net Total</th>
                <th class="text-right font-bold" style="color: #047857;">Gross Profit</th>
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
                    <td class="text-right" style="color: #b45309;">Rs. {{ number_format($row['total_discounts'], 2) }}</td>
                    <td class="text-right font-bold">Rs. {{ number_format($row['net_sales'], 2) }}</td>
                    <td class="text-right font-bold" style="color: #047857;">
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
                    <td class="text-right" style="color: #047857;">
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
</body>
</html>
