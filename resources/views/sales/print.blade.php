<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Invoice #{{ $sale->invoice_number }}</title>
    <style>
        @media print {
            @page {
                size: A5 landscape;
                margin: 0;
            }
            body {
                margin: 5mm 10mm;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: normal;
            color: #000;
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 10px;
        }

        .container {
            width: 100%;
            border: 0px solid #000;
        }

        /* HEADER GRID */
        .header {
            display: flex;
            width: 100%;
            margin-bottom: 2px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        .header-left {
            width: 60%;
            display: flex;
            align-items: center;
        }

        .logo-box {
            border: 3px double #000;
            width: 60px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24pt;
            font-weight: bold;
            font-family: 'Times New Roman', serif;
            margin-right: 10px;
        }

        .company-info {
            text-align: left;
        }

        .company-name {
            font-family: 'Times New Roman', serif;
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }

        .company-desc {
            font-size: 8pt;
            margin-bottom: 2px;
        }

        .company-address {
            font-size: 8pt;
        }

        .header-right {
            width: 40%;
            text-align: right;
            padding-left: 20px;
            font-size: 9pt;
        }

        .header-row {
            display: flex;
            margin-bottom: 5px;
            align-items: flex-end;
        }

        .header-right .header-row {
            justify-content: flex-end !important;
        }

        .header-label {
            white-space: nowrap;
            margin-right: 5px;
        }

        .header-value {
            border-bottom: 1px dotted #000;
            flex-grow: 1;
            padding-left: 5px;
        }

        .header-right .header-value {
            flex-grow: 0;
            min-width: 150px;
        }

        /* INVOICE NO ROW */
        .invoice-row {
            margin-top: 2px;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 9pt;
            display: flex;
            align-items: center;
        }

        .invoice-label {
            margin-right: 5px;
            font-style: italic;
        }

        .invoice-value {
             border-bottom: 1px dotted #000;
             min-width: 100px;
             display: inline-block;
        }

        /* TABLE */
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-bottom: 5px;
        }

        th {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            font-weight: bold;
            text-align: center;
            font-weight: bold;
            background: #f0f0f0;
            font-size: 8pt;
            white-space: nowrap;
        }

        td {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 5px;
            font-size: 8pt;
            vertical-align: middle;
            height: 20px; /* Minimum height for lines */
        }

        .col-name { width: 43%; text-align: left; }
        .col-name { width: 43%; text-align: left; }
        .col-qty { width: 8%; text-align: center; }
        .col-price { width: 16%; text-align: right; }
        .col-disc { width: 15%; text-align: right; }
        .col-total { width: 18%; text-align: right; }

        /* FOOTER GRID */
        .footer {
            display: flex;
            margin-top: 5px;
            align-items: flex-start;
        }

        .footer-left {
            width: 25%;
            text-align: center;
            font-size: 9pt;
        }

        .footer-center {
            width: 45%;
            padding: 0 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .disclaimer-box {
            border: 1px solid #000;
            border-radius: 5px;
            padding: 8px;
            font-size: 8pt;
            text-align: center;
            background: #f5f5f5;
            width: 100%;
        }

        .footer-right {
            width: 30%;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 10pt;
            font-weight: bold;
        }

        .amount-label {
            text-align: left;
        }

        .amount-value {
            text-align: right;
            border-bottom: 1px solid #ccc;
            min-width: 80px;
        }

        .signature-space {
            height: 40px;
            margin-top: 5px;
        }

        /* TOP ACTION BAR (NO-PRINT) */
        .no-print-bar {
            position: sticky;
            top: 8px;
            z-index: 50;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
            padding: 10px 14px;
            margin-bottom: 14px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        .action-bar-inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .invoice-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            font-size: 9pt;
            color: #1e293b;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px 8px;
        }

        .meta-label {
            color: #64748b;
            font-weight: 600;
            font-size: 8pt;
        }

        .meta-value {
            color: #0f172a;
            font-weight: 700;
        }

        .action-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .wa-input-group {
            display: inline-flex;
            align-items: center;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #ffffff;
        }

        .wa-input-group:focus-within {
            border-color: #16a34a;
            box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.15);
        }

        .wa-prefix {
            background: #f1f5f9;
            color: #475569;
            font-size: 8.5pt;
            font-weight: 600;
            padding: 6px 8px;
            border-right: 1px solid #cbd5e1;
            user-select: none;
        }

        .wa-phone-input {
            border: none;
            outline: none;
            padding: 6px 8px;
            font-size: 9pt;
            width: 140px;
            color: #0f172a;
            background: transparent;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            border-radius: 6px;
            padding: 7px 12px;
            font-size: 9pt;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.15s ease, opacity 0.15s ease;
            line-height: 1.2;
        }

        .action-btn-whatsapp {
            background: #16a34a;
            color: #ffffff;
        }

        .action-btn-whatsapp:hover:not(:disabled) {
            background: #15803d;
        }

        .action-btn-whatsapp:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .action-btn-pdf {
            background: #0f172a;
            color: #ffffff;
        }

        .action-btn-pdf:hover {
            background: #1e293b;
        }

        .action-btn-print {
            background: #2563eb;
            color: #ffffff;
        }

        .action-btn-print:hover {
            background: #1d4ed8;
        }

        .wa-spinner {
            width: 12px;
            height: 12px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: #ffffff;
            border-radius: 50%;
            display: inline-block;
            animation: wa-spin 0.7s linear infinite;
        }

        .wa-spinner[hidden] {
            display: none;
        }

        @keyframes wa-spin {
            to { transform: rotate(360deg); }
        }

        .wa-status-banner {
            margin-top: 8px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 8.5pt;
            font-weight: 500;
        }

        .wa-status-banner[hidden] {
            display: none;
        }

        .wa-status-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .wa-status-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

    </style>
</head>
<body>

    <div class="no-print no-print-bar">
        <div class="action-bar-inner">
            <div class="invoice-meta">
                <span class="meta-pill">
                    <span class="meta-label">Invoice</span>
                    <span class="meta-value">#{{ $sale->invoice_number }}</span>
                </span>
                <span class="meta-pill">
                    <span class="meta-label">Total</span>
                    <span class="meta-value">@money($sale->total)</span>
                </span>
                <span class="meta-pill">
                    <span class="meta-label">Customer</span>
                    <span class="meta-value">{{ $sale->customer->name ?? 'Guest' }}</span>
                </span>
            </div>

            <div class="action-controls">
                <div class="wa-input-group">
                    <span class="wa-prefix">+{{ ltrim((string) config('wati.default_country_code', '91'), '+') }}</span>
                    <input
                        id="whatsapp-phone"
                        type="tel"
                        class="wa-phone-input"
                        value="{{ $sale->customer?->phone ?? '' }}"
                        placeholder="WhatsApp number"
                        aria-label="Customer WhatsApp phone number"
                    >
                </div>

                <button
                    id="whatsapp-send-btn"
                    type="button"
                    class="action-btn action-btn-whatsapp"
                    data-send-url="{{ route('sales.send-whatsapp', $sale) }}"
                    disabled
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm5.8 14.07c-.24.68-1.42 1.3-1.97 1.38-.5.08-1.15.11-1.85-.11-.43-.14-.98-.32-1.69-.62-2.98-1.29-4.92-4.3-5.07-4.5-.15-.2-1.21-1.61-1.21-3.07 0-1.46.77-2.18 1.04-2.48.27-.3.6-.37.8-.37h.58c.19 0 .44-.07.68.52.25.6.84 2.06.92 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.39-.45.52-.15.15-.3.31-.13.61.17.3.77 1.27 1.65 2.05 1.13 1.01 2.09 1.32 2.39 1.47.3.15.48.13.66-.08.18-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.27.1 1.74.82 2.04.97.3.15.5.22.57.35.08.13.08.73-.16 1.41z"/>
                    </svg>
                    <span id="whatsapp-spinner" class="wa-spinner" hidden></span>
                    <span id="whatsapp-btn-text">Send via WhatsApp</span>
                </button>

                <a href="{{ route('sales.pdf', $sale) }}" class="action-btn action-btn-pdf">
                    Download as PDF
                </a>

                <button type="button" onclick="window.print()" class="action-btn action-btn-print">
                    Print
                </button>
            </div>
        </div>

        <div id="whatsapp-status-banner" class="wa-status-banner" role="status" aria-live="polite" hidden></div>
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="logo-box">TB</div>
                <div class="company-info">
                    <div class="company-name">{{ \App\Models\Setting::get('store_name', config('app.name')) }}</div>
                    <div class="company-desc">Selling: cosmetics, beauty tools, skincare, and accessories.</div>
                    <div class="company-address">
                        {{ \App\Models\Setting::get('store_address', 'Jl. Default No. 1') }}<br>
                        Phone: {{ format_indian_phone(\App\Models\Setting::get('store_phone', '-')) }}
                        @if(\App\Models\Setting::get('store_email'))
                            <br>Email: {{ \App\Models\Setting::get('store_email') }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="header-right">
                <div class="header-row">
                    <span>{{ $sale->sale_date->locale('en')->isoFormat('dddd, D MMMM Y') }}</span>
                </div>
                <div class="header-row">
                    <span class="header-label">Bill To,</span>
                    <span class="header-value">{{ $sale->customer->name ?? 'Guest' }}</span>
                </div>
            </div>
        </div>

        <!-- Invoice No Line -->
        <div class="invoice-row">
            <span class="invoice-label">INVOICE / CASH RECEIPT No.</span>
            <span class="invoice-value">{{ $sale->invoice_number }}</span>
        </div>

        <!-- Table -->
        <table>
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
                @php
                    $sortedItems = $sale->items->sortBy(fn($item) => $item->service_id ? 1 : 0);
                @endphp
                @foreach($sortedItems as $item)
                @php
                    $finalPrice = $item->unit_price - $item->discount;
                @endphp
                <tr>
                    <td class="col-name">{{ $item->item_name }}</td>
                    <td class="col-qty">{{ $item->quantity }}</td>
                    <td class="col-price">@money($item->unit_price)</td>
                    <td class="col-disc">{!! $item->discount > 0 ? "<span>" . format_money($item->discount) . "</span>" : '-' !!}</td>
                    <td class="col-total">@money($item->subtotal)</td>
                </tr>
                @endforeach

                {{-- Fill empty rows to maintain size --}}
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
                <tr style="background: #f5f5f5; font-weight: bold; border-top: 1.5px solid #000;">
                    <td class="col-name" style="text-align: right; font-weight: bold; padding: 4px 6px;">Total Billed Qty:</td>
                    <td class="col-qty" style="text-align: center; font-weight: bold; font-size: 8.5pt; padding: 4px; background: #eef2ff;">{{ (int)$sale->items->sum('quantity') }}</td>
                    <td colspan="3" style="text-align: right; font-size: 7.5pt; color: #444; padding: 4px 6px;">{{ count($sale->items) }} item line(s)</td>
                </tr>
            </tfoot>
        </table>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-left">
                <div>Received By</div>
                <div class="signature-space"></div>
                <div>( .................................... )</div>
            </div>

            <div class="footer-center">
                <div class="disclaimer-box">
                    Please check that all items are in good condition when received. Purchased items cannot be returned.
                </div>
            </div>

            <div class="footer-right">
                <div class="amount-row" style="background: #f0f4ff; padding: 3px 4px; border: 1px solid #dbeafe; border-radius: 3px; margin-bottom: 6px;">
                    <span class="amount-label" style="color: #1e1b4b; font-size: 9pt;">Total Billed Qty</span>
                    <span class="amount-value" style="color: #1e1b4b; font-weight: 800; border-bottom: none; font-size: 9.5pt;">{{ (int)$sale->items->sum('quantity') }} pcs</span>
                </div>
                <div class="amount-row">
                    <span class="amount-label">Subtotal</span>
                    <span class="amount-value">@money($sale->total + $sale->global_discount)</span>
                </div>
                @php
                    $productItemDiscount = $sale->items->filter(fn($i) => empty($i->service_id))->sum(fn($i) => (float)$i->discount * (int)$i->quantity);
                    $serviceItemDiscount = $sale->items->filter(fn($i) => !empty($i->service_id))->sum(fn($i) => (float)$i->discount * (int)$i->quantity);
                @endphp
                @if($productItemDiscount > 0)
                <div class="amount-row">
                    <span class="amount-label">Product Discount</span>
                    <span class="amount-value">- @money($productItemDiscount)</span>
                </div>
                @endif
                @if($serviceItemDiscount > 0)
                <div class="amount-row">
                    <span class="amount-label">Service Discount</span>
                    <span class="amount-value">- @money($serviceItemDiscount)</span>
                </div>
                @endif
                @php
                    $hasCoupon = ($sale->coupon_discount > 0) || ($sale->coupon_code && $sale->coupon_discount == 0 && $sale->global_discount > 0 && $sale->round_off == 0);
                    $couponDiscountAmount = $sale->coupon_discount > 0 ? $sale->coupon_discount : ($hasCoupon ? $sale->global_discount : 0);
                    $hasRoundOff = ($sale->round_off != 0) || (!$sale->coupon_code && $sale->global_discount != 0 && $sale->round_off == 0);
                    $roundOffAmount = $sale->round_off != 0 ? $sale->round_off : ($hasRoundOff ? -$sale->global_discount : 0);
                @endphp
                @if($hasCoupon && $couponDiscountAmount > 0)
                <div class="amount-row">
                    <span class="amount-label">Coupon ({{ $sale->coupon_code }})</span>
                    <span class="amount-value">- @money($couponDiscountAmount)</span>
                </div>
                @endif
                @if($hasRoundOff && $roundOffAmount != 0)
                <div class="amount-row">
                    <span class="amount-label">Round Off</span>
                    <span class="amount-value">{{ $roundOffAmount < 0 ? '- ' : '+ ' }}@money(abs($roundOffAmount))</span>
                </div>
                @endif
                @if(($sale->return_discount ?? 0) > 0)
                <div class="amount-row">
                    <span class="amount-label">Return Discount</span>
                    <span class="amount-value">- @money($sale->return_discount)</span>
                </div>
                @endif
                <div class="amount-row">
                    <span class="amount-label">Total</span>
                    <span class="amount-value">@money($sale->total)</span>
                </div>
                <div class="amount-row">
                    <span class="amount-label">Payment Mode</span>
                    <span class="amount-value">{{ $sale->payment_method?->label() ?? ucfirst((string) $sale->payment_method) }}</span>
                </div>
                @if($sale->isSplit())
                <div class="amount-row">
                    <span class="amount-label">Cash Paid</span>
                    <span class="amount-value">@money($sale->cash_amount)</span>
                </div>
                <div class="amount-row">
                    <span class="amount-label">UPI / Bank Paid</span>
                    <span class="amount-value">@money($sale->bank_amount)</span>
                </div>
                @if($sale->change > 0)
                <div class="amount-row">
                    <span class="amount-label">Change Returned</span>
                    <span class="amount-value">@money($sale->change)</span>
                </div>
                @endif
                @else
                <div class="amount-row">
                    <span class="amount-label">Amount Received</span>
                    <span class="amount-value">@money($sale->cash_received)</span>
                </div>
                <div class="amount-row">
                    <span class="amount-label">Change</span>
                    <span class="amount-value">@money($sale->change)</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const phoneInput = document.getElementById('whatsapp-phone');
            const sendBtn = document.getElementById('whatsapp-send-btn');
            const btnText = document.getElementById('whatsapp-btn-text');
            const spinner = document.getElementById('whatsapp-spinner');
            const banner = document.getElementById('whatsapp-status-banner');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const sendUrl = sendBtn ? sendBtn.dataset.sendUrl : '';
            let isSending = false;

            function digitCount(value) {
                return (value || '').replace(/\D+/g, '').length;
            }

            function showBanner(type, message) {
                if (!banner) return;
                banner.textContent = message;
                banner.className = 'wa-status-banner ' + (type === 'success' ? 'wa-status-success' : 'wa-status-error');
                banner.hidden = false;
            }

            function hideBanner() {
                if (!banner) return;
                banner.hidden = true;
                banner.textContent = '';
            }

            function syncButtonState() {
                if (!phoneInput || !sendBtn) return;
                sendBtn.disabled = isSending || digitCount(phoneInput.value) < 10;
            }

            if (phoneInput && sendBtn) {
                syncButtonState();

                phoneInput.addEventListener('input', function () {
                    if (!isSending && btnText && btnText.textContent !== 'Send via WhatsApp') {
                        btnText.textContent = 'Send via WhatsApp';
                    }
                    hideBanner();
                    syncButtonState();
                });

                sendBtn.addEventListener('click', async function () {
                    if (isSending || digitCount(phoneInput.value) < 10) {
                        return;
                    }

                    isSending = true;
                    hideBanner();
                    syncButtonState();
                    if (spinner) spinner.hidden = false;
                    if (btnText) btnText.textContent = 'Sending...';

                    try {
                        const response = await fetch(sendUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                phone: phoneInput.value.trim(),
                            }),
                        });

                        const data = await response.json().catch(function () {
                            return {};
                        });

                        if (response.ok && data.success) {
                            if (btnText) btnText.textContent = 'Sent! ✓';
                            showBanner('success', data.message || 'Message sent via WhatsApp');
                        } else {
                            const validationError = data.errors && data.errors.phone && data.errors.phone[0];
                            const errorMsg = validationError || data.message || 'Failed to send WhatsApp message.';
                            if (btnText) btnText.textContent = 'Send via WhatsApp';
                            showBanner('error', errorMsg);
                        }
                    } catch (err) {
                        if (btnText) btnText.textContent = 'Send via WhatsApp';
                        showBanner('error', 'Network error while sending WhatsApp message.');
                    } finally {
                        isSending = false;
                        if (spinner) spinner.hidden = true;
                        syncButtonState();
                    }
                });
            }
        })();
    </script>
</body>
</html>
