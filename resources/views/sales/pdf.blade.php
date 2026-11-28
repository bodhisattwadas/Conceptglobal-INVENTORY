<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $sale->invoice_number }}</title>
    <style>
        @page {
            size: A5 landscape;
            margin: 4mm 8mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7.5pt;
            line-height: 1.15;
            color: #000000;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .container {
            width: 100%;
        }

        /* HEADER */
        table.header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #000000;
            margin-bottom: 2px;
        }

        table.header-table td.header-left-cell {
            width: 60%;
            vertical-align: middle;
            padding: 0 0 2px 0;
        }

        table.header-table td.header-right-cell {
            width: 40%;
            vertical-align: bottom;
            text-align: right;
            padding: 0 0 2px 10px;
            font-size: 7.5pt;
        }

        table.brand-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.brand-table td.logo-cell {
            width: 55px;
            vertical-align: middle;
            padding: 0 8px 0 0;
        }

        .store-logo-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: block;
        }

        .logo-box {
            border: 3px double #000000;
            width: 48px;
            height: 30px;
            line-height: 28px;
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            font-family: 'DejaVu Serif', serif;
        }

        table.brand-table td.company-cell {
            vertical-align: middle;
            text-align: left;
        }

        .company-name {
            font-family: 'DejaVu Serif', serif;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }

        .company-desc {
            font-size: 7pt;
            margin-bottom: 1px;
        }

        .company-address {
            font-size: 7pt;
        }

        .header-date {
            margin-bottom: 2px;
        }

        .header-bill-to {
            margin-bottom: 1px;
        }

        .header-label {
            margin-right: 4px;
        }

        .header-value {
            display: inline-block;
            border-bottom: 1px dotted #000000;
            min-width: 145px;
            padding-left: 4px;
            text-align: right;
        }

        /* INVOICE NO ROW */
        .invoice-row {
            margin-top: 2px;
            margin-bottom: 2px;
            font-weight: bold;
            font-size: 7.8pt;
        }

        .invoice-label {
            margin-right: 5px;
            font-style: italic;
        }

        .invoice-value {
            display: inline-block;
            border-bottom: 1px dotted #000000;
            min-width: 110px;
        }

        /* ITEMS TABLE */
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000000;
            table-layout: fixed;
            margin-bottom: 2px;
        }

        table.items-table tr {
            page-break-inside: avoid;
        }

        table.items-table th {
            border: 1px solid #000000;
            padding: 2px 4px;
            text-align: center;
            font-weight: bold;
            background: #f0f0f0;
            font-size: 7.3pt;
        }

        table.items-table td {
            border-left: 1px solid #000000;
            border-right: 1px solid #000000;
            border-bottom: 1px solid #000000;
            padding: 1.5px 4px;
            font-size: 7.2pt;
            line-height: 1.1;
            vertical-align: middle;
            height: 10px;
            word-wrap: break-word;
        }

        .col-name { width: 43%; text-align: left; }
        .col-qty { width: 8%; text-align: center; }
        .col-price { width: 16%; text-align: right; }
        .col-disc { width: 15%; text-align: right; }
        .col-total { width: 18%; text-align: right; }

        /* FOOTER */
        table.footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            page-break-inside: avoid;
        }

        table.footer-table td.footer-left-cell {
            width: 24%;
            vertical-align: top;
            text-align: center;
            font-size: 7.5pt;
            padding-right: 6px;
        }

        table.footer-table td.footer-center-cell {
            width: 40%;
            vertical-align: middle;
            padding: 0 8px;
        }

        table.footer-table td.footer-right-cell {
            width: 36%;
            vertical-align: top;
        }

        .signature-space {
            height: 18px;
            margin-top: 2px;
        }

        .disclaimer-box {
            border: 1px solid #000000;
            border-radius: 4px;
            padding: 5px 6px;
            font-size: 7pt;
            text-align: center;
            background: #f5f5f5;
        }

        table.amounts-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.amounts-table td {
            padding: 0.5px 3px;
            font-size: 7.2pt;
            line-height: 1.12;
            font-weight: bold;
            vertical-align: middle;
        }

        table.amounts-table td.amount-label {
            text-align: left;
            padding-right: 6px;
            white-space: nowrap;
        }

        table.amounts-table td.amount-value {
            text-align: right;
            border-bottom: 1px solid #cccccc;
            white-space: nowrap;
        }

        table.amounts-table tr.qty-highlight td {
            background: #f0f4ff;
            color: #1e1b4b;
            border-top: 1px solid #dbeafe;
            border-bottom: 1px solid #dbeafe;
            padding: 1.5px 3px;
        }
    </style>
</head>
<body>
    @php
        $sortedItems = $sale->items->sortBy(fn ($item) => $item->service_id ? 1 : 0);
        $productItemDiscount = $sale->items->filter(fn ($i) => empty($i->service_id))->sum(fn ($i) => (float) $i->discount * (int) $i->quantity);
        $serviceItemDiscount = $sale->items->filter(fn ($i) => ! empty($i->service_id))->sum(fn ($i) => (float) $i->discount * (int) $i->quantity);
        $hasCoupon = ($sale->coupon_discount > 0) || ($sale->coupon_code && $sale->coupon_discount == 0 && $sale->global_discount > 0 && $sale->round_off == 0);
        $couponDiscountAmount = $sale->coupon_discount > 0 ? $sale->coupon_discount : ($hasCoupon ? $sale->global_discount : 0);
        $hasRoundOff = ($sale->round_off != 0) || (! $sale->coupon_code && $sale->global_discount != 0 && $sale->round_off == 0);
        $roundOffAmount = $sale->round_off != 0 ? $sale->round_off : ($hasRoundOff ? -$sale->global_discount : 0);
        $logoPath = public_path('images/logo.jpg');
        $logoBase64 = file_exists($logoPath) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    <div class="container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="header-left-cell">
                    <table class="brand-table">
                        <tr>
                            <td class="logo-cell">
                                @if($logoBase64)
                                    <img src="{{ $logoBase64 }}" alt="Logo" class="store-logo-img">
                                @else
                                    <div class="logo-box">TB</div>
                                @endif
                            </td>
                            <td class="company-cell">
                                <div class="company-name">{{ \App\Models\Setting::get('store_name', 'GLAM & GLOW') }}</div>
                                <div class="company-desc">C/O Concept Global Realty LLP</div>
                                <div class="company-address">
                                    1, B K Moitra Rd, Baranagar, West Bengal 700036<br>
                                    Phone: +91 91477 68015<br>
                                    GSTIN: 19AAWFC2199J1ZM
                                    @if(\App\Models\Setting::get('store_email'))
                                        <br>Email: {{ \App\Models\Setting::get('store_email') }}
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="header-right-cell">
                    <div class="header-date">
                        {{ $sale->sale_date->locale('en')->isoFormat('dddd, D MMMM Y') }}
                    </div>
                    <div class="header-bill-to">
                        <span class="header-label">Bill To,</span>
                        <span class="header-value">{{ $sale->customer->name ?? 'Guest' }}</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Invoice No Line -->
        <div class="invoice-row">
            <span class="invoice-label">INVOICE / CASH RECEIPT No.</span>
            <span class="invoice-value">{{ $sale->invoice_number }}</span>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-name">Item Name</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-price">Price</th>
                    <th class="col-disc">Discount</th>
                    <th class="col-total">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sortedItems as $item)
                <tr>
                    <td class="col-name">{{ $item->item_name }}</td>
                    <td class="col-qty">{{ $item->quantity }}</td>
                    <td class="col-price">@money($item->unit_price)</td>
                    <td class="col-disc">{!! $item->discount > 0 ? '<span>'.e(format_money($item->discount)).'</span>' : '-' !!}</td>
                    <td class="col-total">@money($item->subtotal)</td>
                </tr>
                @endforeach

                @for($i = 0; $i < max(0, 8 - count($sale->items)); $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                @endfor
            </tbody>
            <tfoot>
                <tr style="background: #f5f5f5; font-weight: bold; border-top: 1.5px solid #000000;">
                    <td class="col-name" style="text-align: right; font-weight: bold; padding: 2px 5px;">Total Billed Qty:</td>
                    <td class="col-qty" style="text-align: center; font-weight: bold; font-size: 7.5pt; padding: 2px; background: #eef2ff;">{{ (int) $sale->items->sum('quantity') }}</td>
                    <td colspan="3" style="text-align: right; font-size: 6.8pt; color: #444444; padding: 2px 5px;">{{ count($sale->items) }} item line(s)</td>
                </tr>
            </tfoot>
        </table>

        <!-- Footer -->
        <table class="footer-table">
            <tr>
                <td class="footer-left-cell">
                    <div>Received By</div>
                    <div class="signature-space"></div>
                    <div>( .................................... )</div>
                </td>
                <td class="footer-center-cell">
                    <div class="disclaimer-box">
                        Please check that all items are in good condition when received. Purchased items cannot be returned.
                    </div>
                </td>
                <td class="footer-right-cell">
                    <table class="amounts-table">
                        <tr class="qty-highlight">
                            <td class="amount-label" style="font-size: 7.2pt;">Total Billed Qty</td>
                            <td class="amount-value" style="border-bottom: none; font-size: 7.5pt;">{{ (int) $sale->items->sum('quantity') }} pcs</td>
                        </tr>
                        <tr>
                            <td class="amount-label">Subtotal</td>
                            <td class="amount-value">@money($sale->total + $sale->global_discount)</td>
                        </tr>
                        @if($productItemDiscount > 0)
                        <tr>
                            <td class="amount-label">Product Discount</td>
                            <td class="amount-value">- @money($productItemDiscount)</td>
                        </tr>
                        @endif
                        @if($serviceItemDiscount > 0)
                        <tr>
                            <td class="amount-label">Service Discount</td>
                            <td class="amount-value">- @money($serviceItemDiscount)</td>
                        </tr>
                        @endif
                        @if($hasCoupon && $couponDiscountAmount > 0)
                        <tr>
                            <td class="amount-label">Coupon ({{ $sale->coupon_code }})</td>
                            <td class="amount-value">- @money($couponDiscountAmount)</td>
                        </tr>
                        @endif
                        @if($hasRoundOff && $roundOffAmount != 0)
                        <tr>
                            <td class="amount-label">Round Off</td>
                            <td class="amount-value">{{ $roundOffAmount < 0 ? '- ' : '+ ' }}@money(abs($roundOffAmount))</td>
                        </tr>
                        @endif
                        @if(($sale->return_discount ?? 0) > 0)
                        <tr>
                            <td class="amount-label">Return Discount</td>
                            <td class="amount-value">- @money($sale->return_discount)</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="amount-label">Total</td>
                            <td class="amount-value">@money($sale->total)</td>
                        </tr>
                        <tr>
                            <td class="amount-label">Payment Mode</td>
                            <td class="amount-value">{{ $sale->payment_method?->label() ?? ucfirst((string) $sale->payment_method) }}</td>
                        </tr>
                        @if($sale->isSplit())
                        <tr>
                            <td class="amount-label">Cash Paid</td>
                            <td class="amount-value">@money($sale->cash_amount)</td>
                        </tr>
                        <tr>
                            <td class="amount-label">UPI / Bank Paid</td>
                            <td class="amount-value">@money($sale->bank_amount)</td>
                        </tr>
                        @if($sale->change > 0)
                        <tr>
                            <td class="amount-label">Change Returned</td>
                            <td class="amount-value">@money($sale->change)</td>
                        </tr>
                        @endif
                        @else
                        <tr>
                            <td class="amount-label">Amount Received</td>
                            <td class="amount-value">@money($sale->cash_received)</td>
                        </tr>
                        <tr>
                            <td class="amount-label">Change</td>
                            <td class="amount-value">@money($sale->change)</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
