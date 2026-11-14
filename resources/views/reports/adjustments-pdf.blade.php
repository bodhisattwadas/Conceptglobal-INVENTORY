<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Adjustments Report - {{ $storeName }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 10mm 15mm 10mm;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1f2937;
            line-height: 1.35;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .store-name {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
        }
        .store-sub {
            font-size: 8px;
            color: #6b7280;
            margin-top: 2px;
        }
        .report-title {
            text-align: right;
        }
        .report-title h1 {
            font-size: 14px;
            color: #1e40af;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-title p {
            margin: 2px 0 0 0;
            font-size: 8.5px;
            color: #4b5563;
        }
        /* Summary KPI Cards Table */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-bottom: 14px;
        }
        .kpi-card {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 8px;
            background-color: #f9fafb;
            text-align: center;
        }
        .kpi-card.damage {
            border-color: #fecaca;
            background-color: #fef2f2;
        }
        .kpi-card.surplus {
            border-color: #a7f3d0;
            background-color: #f0fdf4;
        }
        .kpi-card.net {
            border-color: #bfdbfe;
            background-color: #eff6ff;
        }
        .kpi-card.gross {
            border-color: #e9d5ff;
            background-color: #faf5ff;
        }
        .kpi-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .damage .kpi-label { color: #991b1b; }
        .surplus .kpi-label { color: #065f46; }
        .net .kpi-label { color: #1e40af; }
        .gross .kpi-label { color: #6b21a8; }
        .kpi-val {
            font-size: 13px;
            font-weight: bold;
            margin: 3px 0;
        }
        .damage .kpi-val { color: #dc2626; }
        .surplus .kpi-val { color: #059669; }
        .net .kpi-val { color: #1d4ed8; }
        .gross .kpi-val { color: #7e22ce; }
        .kpi-sub {
            font-size: 7.5px;
            color: #6b7280;
        }
        /* Main Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        .data-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 4px;
            border: 1px solid #d1d5db;
            text-align: left;
            font-size: 7.5px;
        }
        .data-table td {
            padding: 4px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }
        .badge-damage { background-color: #fee2e2; color: #991b1b; }
        .badge-surplus { background-color: #d1fae5; color: #065f46; }
        .badge-realloc { background-color: #dbeafe; color: #1e40af; }
        .badge-transfer { background-color: #f3e8ff; color: #6b21a8; }
        .footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            font-size: 7.5px;
            color: #9ca3af;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <div class="footer">
        Generated on {{ now()->format('d/m/Y h:i A') }} | {{ $storeName }} | Inventory Adjustments & Valuation Report
    </div>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="store-name">{{ $storeName }}</div>
                <div class="store-sub">
                    {{ $storeAddress }}
                    @if($storePhone !== '-') | Phone: {{ $storePhone }} @endif
                    @if($storeEmail !== '-') | Email: {{ $storeEmail }} @endif
                </div>
            </td>
            <td style="width: 45%; vertical-align: top;" class="report-title">
                <h1>Stock Adjustments Report</h1>
                <p><strong>Period:</strong> {{ $startDate->format('d/m/Y') }} to {{ $endDate->format('d/m/Y') }}</p>
                <p><strong>Total Transactions:</strong> {{ $kpis['total_count'] }} posted</p>
            </td>
        </tr>
    </table>

    <!-- KPI Summary Cards -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card damage" style="width: 25%;">
                <div class="kpi-label">Write-Off / Damage (Loss)</div>
                <div class="kpi-val">-₹{{ number_format($kpis['damage_amount'], 2) }}</div>
                <div class="kpi-sub">{{ $kpis['damage_units'] }} unit(s) across {{ $kpis['damage_count'] }} entry(s)</div>
            </td>
            <td class="kpi-card surplus" style="width: 25%;">
                <div class="kpi-label">Surplus / Found (Gain)</div>
                <div class="kpi-val">+₹{{ number_format($kpis['surplus_amount'], 2) }}</div>
                <div class="kpi-sub">{{ $kpis['surplus_units'] }} unit(s) across {{ $kpis['surplus_count'] }} entry(s)</div>
            </td>
            <td class="kpi-card net" style="width: 25%;">
                <div class="kpi-label">Net Value Impact</div>
                <div class="kpi-val">{{ $kpis['net_amount'] >= 0 ? '+' : '' }}₹{{ number_format($kpis['net_amount'], 2) }}</div>
                <div class="kpi-sub">Net Units: {{ $kpis['net_units'] > 0 ? '+' : '' }}{{ $kpis['net_units'] }}</div>
            </td>
            <td class="kpi-card gross" style="width: 25%;">
                <div class="kpi-label">Total Posted Amount</div>
                <div class="kpi-val">₹{{ number_format($kpis['gross_amount'], 2) }}</div>
                <div class="kpi-sub">Total: {{ $kpis['total_count'] }} posted transaction(s)</div>
            </td>
        </tr>
    </table>

    <!-- Main Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 70px;">Date &amp; Time</th>
                <th style="width: 80px;">Adjustment #</th>
                <th style="width: 80px;">Type</th>
                <th>Product</th>
                <th style="width: 45px;" class="text-center">Location</th>
                <th style="width: 65px;">Batch / PO</th>
                <th style="width: 85px;">Reason</th>
                <th style="width: 45px;" class="text-right">Qty</th>
                <th style="width: 60px;" class="text-right">Unit Cost (₹)</th>
                <th style="width: 65px;" class="text-right">Total (₹)</th>
                <th style="width: 65px;">Posted By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($adjustments as $adj)
                @php
                    $isDamage = $adj->type === 'quantity_decrease';
                    $isSurplus = $adj->type === 'quantity_increase';
                    $isRealloc = $adj->type === 'reallocation';
                    $isTransfer = $adj->type === 'location_transfer';
                    $total = (int) $adj->quantity * (float) $adj->unit_cost;
                    $loc = ($adj->source_location?->value ?? ($adj->target_location?->value ?? 'shop'));
                @endphp
                <tr>
                    <td>{{ $adj->created_at?->format('d/m/Y H:i') ?: '-' }}</td>
                    <td class="text-bold">{{ $adj->adjustment_number }}</td>
                    <td>
                        @if($isDamage)
                            <span class="badge badge-damage">Write-Off</span>
                        @elseif($isSurplus)
                            <span class="badge badge-surplus">Surplus</span>
                        @elseif($isRealloc)
                            <span class="badge badge-realloc">Realloc</span>
                        @elseif($isTransfer)
                            <span class="badge badge-transfer">Movement</span>
                        @else
                            <span class="badge">{{ ucfirst((string) $adj->type) }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="text-bold">{{ $adj->sourceProduct?->name ?: 'N/A' }}</div>
                        <div style="font-size: 7px; color: #6b7280;">
                            @if($adj->sourceProduct?->product_code) Code: {{ $adj->sourceProduct->product_code }} @endif
                            @if($adj->sourceProduct?->sku) | SKU: {{ $adj->sourceProduct->sku }} @endif
                        </div>
                    </td>
                    <td class="text-center">
                        {{ $loc === 'parlour' ? 'Parlour' : 'Store' }}
                    </td>
                    <td>
                        <div>{{ $adj->sourcePurchaseItem?->batch_number ?: '-' }}</div>
                        @if($adj->sourcePurchaseItem?->purchase)
                            <div style="font-size: 7px; color: #6b7280;">PO: {{ $adj->sourcePurchaseItem->purchase->invoice_number }}</div>
                        @endif
                    </td>
                    <td>
                        {{ app(\App\Services\InventoryAdjustmentReportService::class)->formatReasonLabel($adj->reason) }}
                        @if($adj->notes)
                            <div style="font-size: 7px; color: #6b7280;">{{ $adj->notes }}</div>
                        @endif
                    </td>
                    <td class="text-right text-bold">
                        {{ $isDamage ? '-' : ($isSurplus ? '+' : '') }}{{ $adj->quantity }}
                    </td>
                    <td class="text-right">
                        {{ number_format((float) $adj->unit_cost, 2) }}
                    </td>
                    <td class="text-right text-bold" style="{{ $isDamage ? 'color: #dc2626;' : ($isSurplus ? 'color: #059669;' : '') }}">
                        {{ $isDamage ? '-' : ($isSurplus ? '+' : '') }}₹{{ number_format($total, 2) }}
                    </td>
                    <td>{{ $adj->creator?->name ?: 'System' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center" style="padding: 20px; color: #6b7280;">
                        No stock adjustments found for this period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f3f4f6; font-weight: bold; border-top: 2px solid #9ca3af;">
                <td colspan="7" class="text-right">TOTAL POSTED AMOUNT:</td>
                <td class="text-right">{{ $kpis['damage_units'] + $kpis['surplus_units'] }}</td>
                <td></td>
                <td class="text-right">₹{{ number_format($kpis['gross_amount'], 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
