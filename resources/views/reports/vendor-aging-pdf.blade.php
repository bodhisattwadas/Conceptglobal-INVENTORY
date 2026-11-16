<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Aging Report - {{ $storeName }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 10mm 12mm 10mm;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #1f2937;
            line-height: 1.35;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .store-name {
            font-size: 15px;
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
            font-size: 13px;
            color: #1e40af;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-title p {
            margin: 2px 0 0 0;
            font-size: 8px;
            color: #4b5563;
        }
        /* KPI Cards Table */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px 0;
            margin-bottom: 12px;
        }
        .kpi-card {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 6px;
            background-color: #f9fafb;
            text-align: center;
        }
        .kpi-card.total {
            border-color: #bfdbfe;
            background-color: #eff6ff;
        }
        .kpi-card.b0 {
            border-color: #a7f3d0;
            background-color: #f0fdf4;
        }
        .kpi-card.b31 {
            border-color: #fde68a;
            background-color: #fffbeb;
        }
        .kpi-card.b61 {
            border-color: #fed7aa;
            background-color: #fff7ed;
        }
        .kpi-card.b90 {
            border-color: #fecaca;
            background-color: #fef2f2;
        }
        .kpi-label {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .total .kpi-label { color: #1e40af; }
        .b0 .kpi-label { color: #065f46; }
        .b31 .kpi-label { color: #92400e; }
        .b61 .kpi-label { color: #9a3412; }
        .b90 .kpi-label { color: #991b1b; }
        .kpi-val {
            font-size: 11px;
            font-weight: bold;
            margin: 2px 0;
        }
        .total .kpi-val { color: #1d4ed8; }
        .b0 .kpi-val { color: #059669; }
        .b31 .kpi-val { color: #d97706; }
        .b61 .kpi-val { color: #ea580c; }
        .b90 .kpi-val { color: #dc2626; }
        .kpi-sub {
            font-size: 7px;
            color: #6b7280;
        }

        /* Section Headings */
        .section-header {
            font-size: 10px;
            font-weight: bold;
            color: #1f2937;
            margin: 8px 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 2px;
        }

        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 12px;
        }
        .data-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5px;
            padding: 4px 5px;
            border: 1px solid #d1d5db;
        }
        .data-table td {
            padding: 3.5px 5px;
            border: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f9fafb;
        }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .text-rose { color: #dc2626; }
        .text-emerald { color: #059669; }
        .text-amber { color: #d97706; }
        .text-orange { color: #ea580c; }
        .text-blue { color: #1d4ed8; }
    </style>
</head>
<body>
    <!-- Top Header -->
    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="store-name">{{ $storeName }}</div>
                <div class="store-sub">{{ $storeAddress }}</div>
                <div class="store-sub">Phone: {{ $storePhone }} | Email: {{ $storeEmail }}</div>
            </td>
            <td style="width: 45%; vertical-align: top;" class="report-title">
                <h1>Accounts Payable Vendor Aging</h1>
                <p>Generated At: {{ $generatedAt }}</p>
                @if(!empty($filters['supplier_name']))
                    <p>Vendor: {{ $filters['supplier_name'] }}</p>
                @endif
                @if(!empty($filters['bucket']) && $filters['bucket'] !== 'all')
                    <p>Bucket: {{ $filters['bucket'] }}</p>
                @endif
            </td>
        </tr>
    </table>

    <!-- Summary KPI Cards -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card total" style="width: 18%;">
                <div class="kpi-label">Total Outstanding</div>
                <div class="kpi-val">{{ format_money($kpis['total_due']) }}</div>
                <div class="kpi-sub">{{ $kpis['invoices_count'] }} Invoices / {{ $kpis['vendors_count'] }} Vendors</div>
            </td>
            <td class="kpi-card b0" style="width: 16%;">
                <div class="kpi-label">0–30 Days (Current)</div>
                <div class="kpi-val">{{ format_money($kpis['bucket_0_30_due']) }}</div>
                <div class="kpi-sub">Prompt Payment</div>
            </td>
            <td class="kpi-card b31" style="width: 16%;">
                <div class="kpi-label">31–60 Days</div>
                <div class="kpi-val">{{ format_money($kpis['bucket_31_60_due']) }}</div>
                <div class="kpi-sub">Past Due (1 Mo.)</div>
            </td>
            <td class="kpi-card b61" style="width: 16%;">
                <div class="kpi-label">61–90 Days</div>
                <div class="kpi-val">{{ format_money($kpis['bucket_61_90_due']) }}</div>
                <div class="kpi-sub">Past Due (2 Mos.)</div>
            </td>
            <td class="kpi-card b90" style="width: 16%;">
                <div class="kpi-label">90+ Days</div>
                <div class="kpi-val">{{ format_money($kpis['bucket_90_plus_due']) }}</div>
                <div class="kpi-sub">Severely Overdue</div>
            </td>
            <td class="kpi-card" style="width: 18%;">
                <div class="kpi-label" style="color: #4b5563;">Settlement Status</div>
                <div class="kpi-val" style="color: #111827;">{{ $kpis['invoices_count'] }}</div>
                <div class="kpi-sub">Open Invoices across {{ $kpis['vendors_count'] }} Vendors</div>
            </td>
        </tr>
    </table>

    <!-- Vendor Summary Breakdown -->
    <div class="section-header">Vendor Breakdown</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-left" style="width: 25%;">Vendor / Supplier</th>
                <th class="text-left" style="width: 15%;">Contact</th>
                <th class="text-center" style="width: 8%;">Invoices</th>
                <th class="text-right" style="width: 13%;">0–30 Days</th>
                <th class="text-right" style="width: 13%;">31–60 Days</th>
                <th class="text-right" style="width: 13%;">61–90 Days</th>
                <th class="text-right" style="width: 13%;">90+ Days</th>
                <th class="text-right" style="width: 15%;">Total Due</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vendorBreakdown as $row)
                <tr>
                    <td class="font-bold">{{ $row['supplier_name'] }}</td>
                    <td>{{ $row['contact_person'] }}</td>
                    <td class="text-center">{{ $row['invoice_count'] }}</td>
                    <td class="text-right text-emerald">{{ $row['bucket_0_30'] > 0 ? format_money($row['bucket_0_30']) : '-' }}</td>
                    <td class="text-right text-amber">{{ $row['bucket_31_60'] > 0 ? format_money($row['bucket_31_60']) : '-' }}</td>
                    <td class="text-right text-orange">{{ $row['bucket_61_90'] > 0 ? format_money($row['bucket_61_90']) : '-' }}</td>
                    <td class="text-right text-rose">{{ $row['bucket_90_plus'] > 0 ? format_money($row['bucket_90_plus']) : '-' }}</td>
                    <td class="text-right font-bold text-blue">{{ format_money($row['total_due']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 12px; color: #6b7280;">No outstanding vendor payables.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($vendorBreakdown) > 0)
            <tfoot>
                <tr style="background-color: #f3f4f6; font-weight: bold; border-top: 2px solid #9ca3af;">
                    <td colspan="2" class="text-left">TOTAL OUTSTANDING</td>
                    <td class="text-center">{{ $kpis['invoices_count'] }}</td>
                    <td class="text-right text-emerald">{{ format_money($kpis['bucket_0_30_due']) }}</td>
                    <td class="text-right text-amber">{{ format_money($kpis['bucket_31_60_due']) }}</td>
                    <td class="text-right text-orange">{{ format_money($kpis['bucket_61_90_due']) }}</td>
                    <td class="text-right text-rose">{{ format_money($kpis['bucket_90_plus_due']) }}</td>
                    <td class="text-right text-blue">{{ format_money($kpis['total_due']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <!-- Detailed Invoices Listing -->
    <div class="section-header">Detailed Invoice Listing ({{ count($invoices) }} Invoices)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-left" style="width: 12%;">Invoice #</th>
                <th class="text-left" style="width: 14%;">PO Ref</th>
                <th class="text-left" style="width: 20%;">Vendor / Supplier</th>
                <th class="text-center" style="width: 10%;">Effective Date</th>
                <th class="text-center" style="width: 8%;">Age</th>
                <th class="text-center" style="width: 10%;">Bracket</th>
                <th class="text-center" style="width: 8%;">Status</th>
                <th class="text-right" style="width: 10%;">Amount</th>
                <th class="text-right" style="width: 10%;">Paid</th>
                <th class="text-right" style="width: 12%;">Due Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $inv)
                <tr>
                    <td class="font-bold">{{ $inv->invoice_number ?: '-' }}</td>
                    <td>{{ $inv->po_reference }}</td>
                    <td>{{ $inv->supplier?->name ?: '-' }}</td>
                    <td class="text-center">{{ $inv->effective_date->format('d/m/Y') }}</td>
                    <td class="text-center">{{ $inv->age_in_days }} d</td>
                    <td class="text-center font-bold {{ $inv->aging_bucket === '90+' ? 'text-rose' : ($inv->aging_bucket === '61-90' ? 'text-orange' : ($inv->aging_bucket === '31-60' ? 'text-amber' : 'text-emerald')) }}">
                        {{ $inv->aging_bucket_label }}
                    </td>
                    <td class="text-center">{{ $inv->status instanceof \App\Enums\VendorInvoiceStatus ? $inv->status->label() : ucfirst((string) $inv->status) }}</td>
                    <td class="text-right">{{ format_money($inv->amount) }}</td>
                    <td class="text-right">{{ format_money($inv->paid_amount) }}</td>
                    <td class="text-right font-bold text-rose">{{ format_money($inv->due_amount) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 12px; color: #6b7280;">No open invoices found.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($invoices) > 0)
            <tfoot>
                <tr style="background-color: #f3f4f6; font-weight: bold; border-top: 2px solid #9ca3af;">
                    <td colspan="7" class="text-left">TOTAL DUE</td>
                    <td class="text-right">{{ format_money($invoices->sum('amount')) }}</td>
                    <td class="text-right">{{ format_money($invoices->sum('paid_amount')) }}</td>
                    <td class="text-right text-rose">{{ format_money($invoices->sum('due_amount')) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
