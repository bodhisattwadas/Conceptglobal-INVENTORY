<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order #{{ $purchase->invoice_number ?: $purchase->id }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111827; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 18px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .title { font-size: 22px; font-weight: bold; margin: 0; }
        .store-name { font-size: 18px; font-weight: bold; margin-bottom: 4px; }
        .muted { color: #6b7280; }
        .small-line { margin: 2px 0; }
        .grid { width: 100%; margin-bottom: 18px; }
        .grid td { width: 50%; vertical-align: top; padding: 5px 0; }
        .label { font-size: 10px; color: #6b7280; text-transform: uppercase; }
        .value { font-weight: bold; margin-top: 2px; }
        .panel-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .panel-table td { width: 50%; vertical-align: top; border: 1px solid #d1d5db; padding: 10px; }
        .panel-title { font-size: 11px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; color: #374151; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        table.items td { border: 1px solid #d1d5db; padding: 8px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .total { font-size: 15px; font-weight: bold; }
        .positive { color: #047857; }
        .negative { color: #b91c1c; }
        .notes { margin-top: 18px; border: 1px solid #d1d5db; padding: 10px; background: #f9fafb; }
        .disclaimer { margin-top: 18px; text-align: center; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    @php
        $storeName = \App\Models\Setting::get('store_name', config('app.name'));
        $storeAddress = \App\Models\Setting::get('store_address', '-');
        $storePhone = \App\Models\Setting::get('store_phone', '-');
        $storeEmail = \App\Models\Setting::get('store_email', '-');
        $vendorInvoice = $purchase->vendorInvoice;
        $vendorDiscount = (float) ($vendorInvoice?->vendor_additional_discount ?? 0);
        $hasVendorDiscount = $vendorDiscount != 0.0;
        $vendorDiscountClass = $vendorDiscount > 0 ? 'positive' : 'negative';
        $vendorDiscountLabel = ($vendorDiscount > 0 ? '+ ' : '') . format_money($vendorDiscount);
        $receivedValue = $vendorInvoice
            ? (float) $vendorInvoice->amount + $vendorDiscount
            : $purchase->items->sum(fn ($item) => ((int) ($item->received_quantity ?? 0)) * ((float) $item->unit_price));
        $supplierAddress = collect([
            $purchase->supplier?->address_line_1,
            $purchase->supplier?->address_line_2,
            $purchase->supplier?->city,
            $purchase->supplier?->state,
            $purchase->supplier?->postal_code,
            $purchase->supplier?->country,
        ])->filter()->join(', ') ?: ($purchase->supplier?->address ?: '-');
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="store-name">{{ $storeName }}</div>
                    <p class="small-line">{{ $storeAddress }}</p>
                    <p class="small-line">Phone: {{ format_indian_phone($storePhone) }}</p>
                    <p class="small-line">Email: {{ $storeEmail }}</p>
                </td>
                <td class="right">
                    <p class="title">Purchase Order</p>
                    <p class="muted">#{{ $purchase->invoice_number ?: $purchase->id }}</p>
                </td>
            </tr>
        </table>
    </div>

    <table class="panel-table">
        <tr>
            <td colspan="2">
                <div class="panel-title">Order From / Supplier</div>
                <div class="value">{{ $purchase->supplier->name ?? '-' }}</div>
                <p class="small-line">Contact: {{ $purchase->supplier->contact_person ?: '-' }}</p>
                <p class="small-line">Phone: {{ format_indian_phone($purchase->supplier->phone) }}</p>
                <p class="small-line">Email: {{ $purchase->supplier->email ?: '-' }}</p>
                <p class="small-line">GST / Tax ID: {{ $purchase->supplier->tax_id ?: '-' }}</p>
                <p class="small-line">Address: {{ $supplierAddress }}</p>
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td>
                <div class="label">PO Reference</div>
                <div class="value">{{ $purchase->invoice_number ?: '-' }}</div>
            </td>
            <td>
                <div class="label">PO Date</div>
                <div class="value">{{ $purchase->purchase_date?->format('d M Y') ?? '-' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Expected Delivery</div>
                <div class="value">{{ $purchase->due_date?->format('d M Y') ?? '-' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Status</div>
                <div class="value">{{ $purchase->status->label() }}</div>
            </td>
            <td>
                <div class="label">Created By</div>
                <div class="value">{{ $purchase->creator->name ?? '-' }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Code</th>
                <th>Product</th>
                <th>Brand</th>
                <th class="center">Qty</th>
                <th class="right">MRP</th>
                <th class="right">Order Price</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $item)
                <tr>
                    <td>{{ $item->product->sku ?? '-' }}</td>
                    <td>{{ $item->product->name ?? '-' }}</td>
                    <td>{{ $item->product->company->short_name ?? $item->product->company->company_name ?? '-' }}</td>
                    <td class="center">{{ number_format($item->quantity) }}</td>
                    <td class="right">{{ format_money($item->selling_price) }}</td>
                    <td class="right">{{ format_money($item->unit_price) }}</td>
                    <td class="right">{{ format_money($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="right total">Total PO</td>
                <td class="right total">{{ format_money($purchase->total) }}</td>
            </tr>
            @if($vendorInvoice)
                <tr>
                    <td colspan="6" class="right total">PO Received Value</td>
                    <td class="right total">{{ format_money($receivedValue) }}</td>
                </tr>
                @if($hasVendorDiscount)
                    <tr>
                        <td colspan="6" class="right total">Vendor Additional Discount</td>
                        <td class="right total {{ $vendorDiscountClass }}">{{ $vendorDiscountLabel }}</td>
                    </tr>
                @endif
                <tr>
                    <td colspan="6" class="right total">Final Invoice Value</td>
                    <td class="right total">{{ format_money($vendorInvoice->amount) }}</td>
                </tr>
            @endif
        </tfoot>
    </table>

    <div class="notes">
        <div class="label">Notes</div>
        <div>{{ $purchase->notes ?: 'No additional notes.' }}</div>
    </div>

    <div class="disclaimer">
        This is a computer-generated purchase order. No signature is required.
    </div>
</body>
</html>
