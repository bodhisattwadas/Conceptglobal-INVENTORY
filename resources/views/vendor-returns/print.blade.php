<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debit Note / Vendor Return #{{ $vendorReturn->return_number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111827; margin: 20px; }
        .header { border-bottom: 2px solid #e11d48; padding-bottom: 12px; margin-bottom: 18px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .title { font-size: 22px; font-weight: bold; margin: 0; color: #e11d48; }
        .store-name { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .muted { color: #6b7280; font-size: 11px; }
        .small-line { margin: 2px 0; }
        .panel-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .panel-table td { width: 50%; vertical-align: top; border: 1px solid #d1d5db; padding: 10px; }
        .panel-title { font-size: 11px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; color: #374151; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th { background: #fdf2f8; border: 1px solid #d1d5db; padding: 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        table.items td { border: 1px solid #d1d5db; padding: 8px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .total-row { background: #f9fafb; font-weight: bold; }
        .total-amount { font-size: 15px; font-weight: bold; color: #e11d48; }
        .signatures { margin-top: 40px; width: 100%; border-collapse: collapse; }
        .signatures td { width: 33.33%; text-align: center; vertical-align: bottom; height: 60px; font-size: 11px; }
        .sig-line { border-top: 1px solid #9ca3af; width: 80%; margin: 0 auto 5px auto; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    @php
        $storeName = \App\Models\Setting::get('store_name', config('app.name'));
        $storeAddress = \App\Models\Setting::get('store_address', '-');
        $storePhone = \App\Models\Setting::get('store_phone', '-');
        $storeEmail = \App\Models\Setting::get('store_email', '-');
        $supplier = $vendorReturn->supplier;
        $supplierAddress = collect([
            $supplier?->address_line_1,
            $supplier?->address_line_2,
            $supplier?->city,
            $supplier?->state,
            $supplier?->postal_code,
        ])->filter()->join(', ') ?: ($supplier?->address ?: '-');
    @endphp

    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 6px 14px; background: #e11d48; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">
            Print / Save PDF
        </button>
    </div>

    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="store-name">{{ $storeName }}</div>
                    <div class="muted small-line">{{ $storeAddress }}</div>
                    <div class="muted small-line">Phone: {{ $storePhone }} | Email: {{ $storeEmail }}</div>
                </td>
                <td class="right">
                    <div class="title">DEBIT NOTE</div>
                    <div class="small-line"><strong>Return #:</strong> {{ $vendorReturn->return_number }}</div>
                    <div class="muted small-line">Date: {{ $vendorReturn->return_date?->format('d M Y') }}</div>
                    <div class="muted small-line">Original PO: {{ $vendorReturn->purchase?->invoice_number ?: 'PO #'.$vendorReturn->purchase_id }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="panel-table">
        <tr>
            <td>
                <div class="panel-title">To (Vendor / Supplier):</div>
                <div style="font-weight: bold; font-size: 13px;">{{ $supplier?->name }}</div>
                @if($supplier?->contact_person)
                    <div class="muted small-line">Attn: {{ $supplier->contact_person }}</div>
                @endif
                <div class="muted small-line">{{ $supplierAddress }}</div>
                @if($supplier?->phone)
                    <div class="muted small-line">Phone: {{ $supplier->phone }}</div>
                @endif
                @if($supplier?->tax_id)
                    <div class="muted small-line">GSTIN/Tax ID: {{ $supplier->tax_id }}</div>
                @endif
            </td>
            <td>
                <div class="panel-title">Return Summary:</div>
                <div class="small-line"><strong>Reason:</strong> {{ $vendorReturn->reason_label }}</div>
                <div class="small-line"><strong>Location:</strong> {{ strtoupper($vendorReturn->location) }}</div>
                <div class="small-line"><strong>Brand:</strong> {{ $vendorReturn->company?->company_name ?: '-' }}</div>
                @if($vendorReturn->notes)
                    <div class="muted small-line" style="margin-top: 4px;"><strong>Remarks:</strong> {{ $vendorReturn->notes }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Product Description</th>
                <th style="width: 15%;">Batch #</th>
                <th class="center" style="width: 10%;">Returned Qty</th>
                <th class="right" style="width: 15%;">Unit Cost</th>
                <th class="right" style="width: 15%;">Total Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($vendorReturn->items as $idx => $item)
                <tr>
                    <td class="center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $item->product?->name }}</strong>
                        @if($item->product?->sku)
                            <div class="muted">SKU: {{ $item->product->sku }}</div>
                        @endif
                    </td>
                    <td style="font-family: monospace;">{{ $item->batch_number ?: '-' }}</td>
                    <td class="center"><strong>{{ $item->quantity }}</strong></td>
                    <td class="right">{{ format_money($item->unit_price) }}</td>
                    <td class="right"><strong>{{ format_money($item->subtotal) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" class="right">Total Quantity:</td>
                <td class="center">{{ $vendorReturn->items->sum('quantity') }}</td>
                <td class="right">Total Debit:</td>
                <td class="right total-amount">{{ $vendorReturn->formatted_total }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 15px; font-size: 11px; color: #4b5563;">
        <em>Note: This debit note acknowledges that the above materials have been returned to the vendor. The amount of {{ $vendorReturn->formatted_total }} will be adjusted against our accounts payable / future bills.</em>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line"></div>
                Prepared By ({{ $vendorReturn->creator?->name }})
            </td>
            <td>
                <div class="sig-line"></div>
                Authorized Signatory
            </td>
            <td>
                <div class="sig-line"></div>
                Vendor / Courier Signature
            </td>
        </tr>
    </table>
</body>
</html>
