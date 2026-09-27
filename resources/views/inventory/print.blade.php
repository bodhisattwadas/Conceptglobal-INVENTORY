<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Stock Report #{{ $reportCode }}</title>
    <style>
        @page { margin: 20px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #0f172a;
            background: #ffffff;
        }
        .sheet {
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            overflow: hidden;
        }
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #0ea5e9 100%);
            color: #ffffff;
            padding: 20px 24px;
        }
        .hero-table {
            width: 100%;
            border-collapse: collapse;
        }
        .hero-table td {
            vertical-align: top;
        }
        .brand {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 6px 0;
        }
        .muted {
            color: rgba(255, 255, 255, 0.8);
        }
        .report-title {
            font-size: 28px;
            font-weight: 800;
            margin: 0;
            text-align: right;
        }
        .report-code {
            text-align: right;
            margin-top: 4px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.86);
        }
        .meta {
            padding: 14px 24px 6px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-bottom: 8px;
        }
        .summary-grid td {
            width: 33.333%;
            background: #ffffff;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            padding: 12px 14px;
        }
        .summary-label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            margin-bottom: 4px;
        }
        .summary-value {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
        }
        .summary-accent {
            color: #2563eb;
        }
        .summary-warning {
            color: #dc2626;
        }
        .summary-success {
            color: #059669;
        }
        .right {
            text-align: right;
        }
        .table-wrap {
            padding: 18px 24px 6px;
        }
        table.inventory {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #dbe4f0;
            overflow: hidden;
        }
        table.inventory thead th {
            background: #111827;
            color: #ffffff;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 11px 10px;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            text-align: left;
        }
        table.inventory thead th:last-child {
            border-right: 0;
        }
        table.inventory tbody td {
            border-top: 1px solid #e5e7eb;
            padding: 10px;
            vertical-align: top;
        }
        table.inventory tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .product-name {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .sku {
            font-size: 10px;
            color: #64748b;
        }
        .chip {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            border: 1px solid transparent;
        }
        .chip-blue {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }
        .chip-emerald {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .chip-rose {
            background: #fff1f2;
            color: #be123c;
            border-color: #fecdd3;
        }
        .qty-box {
            font-weight: 800;
            color: #0f172a;
        }
        .qty-sub {
            display: block;
            margin-top: 2px;
            font-size: 10px;
            color: #64748b;
        }
        .status-low {
            color: #be123c;
            background: #fff1f2;
            border: 1px solid #fecdd3;
        }
        .status-ok {
            color: #047857;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
        }
        .footer {
            padding: 10px 24px 18px;
            color: #64748b;
            font-size: 10px;
        }
        .footer-line {
            margin-top: 14px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="hero">
            <table class="hero-table">
                <tr>
                    <td>
                        <div class="brand">{{ $storeName }}</div>
                        <div class="muted">{{ $storeAddress }}</div>
                        <div class="muted">Phone: {{ format_indian_phone($storePhone) }} | Email: {{ $storeEmail }}</div>
                    </td>
                    <td>
                        <p class="report-title">Inventory Stock Report</p>
                        <div class="report-code">{{ $reportCode }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="meta">
            <table class="summary-grid">
                <tr>
                    <td>
                        <span class="summary-label">Total Products</span>
                        <span class="summary-value summary-accent">{{ number_format($summary['totalProducts']) }}</span>
                    </td>
                    <td>
                        <span class="summary-label">Low Stock</span>
                        <span class="summary-value summary-warning">{{ number_format($summary['lowStockCount']) }}</span>
                    </td>
                    <td>
                        <span class="summary-label">In Stock</span>
                        <span class="summary-value summary-success">{{ number_format($summary['inStockCount']) }}</span>
                    </td>
                </tr>
            </table>
            <div class="muted">Generated at {{ $generatedAt->format('d M Y, h:i a') }}</div>
        </div>

        <div class="table-wrap">
            <table class="inventory">
                <thead>
                    <tr>
                        <th style="width: 18%;">SKU</th>
                        <th style="width: 28%;">Product</th>
                        <th style="width: 15%;">Brand / Company</th>
                        <th style="width: 15%;">Category</th>
                        <th style="width: 9%;" class="right">Qty</th>
                        <th style="width: 9%;" class="right">Min Qty</th>
                        <th style="width: 6%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stocks as $stock)
                        @php
                            $minStock = (int) ($stock->product?->min_stock ?? 0);
                            $isLow = $stock->quantity <= $minStock;
                        @endphp
                        <tr>
                            <td>
                                <div class="product-name">{{ $stock->product?->sku ?: '-' }}</div>
                            </td>
                            <td>
                                <div class="product-name">{{ $stock->product?->name ?: '-' }}</div>
                                <div class="sku">{{ $stock->product?->unit?->symbol ?: $stock->product?->unit?->name ?: '-' }}</div>
                            </td>
                            <td>{{ $stock->product?->company?->short_name ?: $stock->product?->company?->company_name ?: '-' }}</td>
                            <td>{{ $stock->product?->category?->name ?: '-' }}</td>
                            <td class="right">
                                <div class="qty-box">{{ number_format($stock->quantity) }}</div>
                                <span class="qty-sub">{{ $stock->product?->unit?->symbol ?: $stock->product?->unit?->name ?: 'pcs' }}</span>
                            </td>
                            <td class="right">{{ number_format($minStock) }}</td>
                            <td>
                                <span class="chip {{ $isLow ? 'status-low' : 'status-ok' }}">
                                    {{ $isLow ? 'Low' : 'OK' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="footer">
            <div class="footer-line">
                <span>This inventory report is computer-generated.</span>
                <span>Page 1</span>
            </div>
        </div>
    </div>
</body>
</html>