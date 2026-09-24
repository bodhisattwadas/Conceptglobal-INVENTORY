<x-app-layout title="Vendor Invoice Details">
    @php
        $dueAmount = max(0, (float) $vendorInvoice->amount - (float) $vendorInvoice->paid_amount);
        $vendorDiscount = (float) $vendorInvoice->vendor_additional_discount;
        $vendorDiscountClass = $vendorDiscount > 0 ? 'text-emerald-700' : 'text-red-700';
        $vendorDiscountLabel = ($vendorDiscount > 0 ? '+ ' : '') . format_money($vendorDiscount);
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Vendor Invoice') }} #{{ $vendorInvoice->invoice_number ?: $vendorInvoice->po_reference }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('Track supplier invoice document, payable amount, and completed payment details.') }}</p>
            </div>
            <a href="{{ route('vendor-invoices.index') }}" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                {{ __('Back') }}
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <section class="rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm sm:px-8 lg:col-span-2 lg:px-10">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Payable Amount') }}</p>
                            <p class="mt-1 text-3xl font-bold text-gray-900">{{ format_money($vendorInvoice->amount) }}</p>
                            <p class="mt-2 flex flex-wrap items-center gap-2 text-sm font-semibold">
                                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700">
                                    {{ __('Paid') }}: {{ format_money($vendorInvoice->paid_amount) }}
                                </span>
                                <span class="inline-flex rounded-full border px-2.5 py-1 {{ $dueAmount > 0 ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                    {{ __('Due') }}: {{ format_money($dueAmount) }}
                                </span>
                            </p>
                        </div>
                        <span class="inline-flex rounded-full border px-3 py-1 text-sm font-semibold shadow-sm {{ $vendorInvoice->status->color() }}">
                            {{ $vendorInvoice->status->label() }}
                        </span>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-8 text-sm md:grid-cols-2">
                        <div class="space-y-5">
                            <x-detail-item label="PO Reference" :value="$vendorInvoice->po_reference" />
                            <x-detail-item label="Vendor Invoice Number" :value="$vendorInvoice->invoice_number ?: '-'" />
                            <x-detail-item label="Invoice Date" :value="$vendorInvoice->invoice_date?->format('d/m/Y') ?: '-'" />
                            <x-detail-item label="Order Received Date" :value="$vendorInvoice->order_received_date?->format('d/m/Y') ?: '-'" />
                            @if($vendorDiscount != 0.0)
                                <div class="flex flex-col space-y-1">
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Vendor Additional Discount') }}</dt>
                                    <dd class="text-base font-medium {{ $vendorDiscountClass }}">{{ $vendorDiscountLabel }}</dd>
                                </div>
                            @endif
                        </div>
                        <div class="space-y-5">
                            <x-detail-item label="Supplier / Vendor" :value="$vendorInvoice->supplier?->name ?: '-'" />
                            <x-detail-item label="Brand / Company" :value="$vendorInvoice->company?->short_name ?: $vendorInvoice->company?->company_name ?: '-'" />
                            <x-detail-item label="Created" :value="$vendorInvoice->created_at?->format('d/m/Y h:i A') ?: '-'" />
                        </div>
                    </div>

                    <div class="mt-7 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-5">
                        <a href="{{ route('purchases.show', $vendorInvoice->purchase) }}" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            <x-heroicon-o-eye class="h-4 w-4" />
                            {{ __('View PO') }}
                        </a>
                        @if($vendorInvoice->document_path)
                            <a href="{{ public_storage_url($vendorInvoice->document_path) }}" target="_blank" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-blue-200 bg-blue-50 px-3 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                                <x-heroicon-o-paper-clip class="h-4 w-4" />
                                {{ __('Open Invoice File') }}
                            </a>
                        @else
                            <span class="inline-flex h-9 items-center rounded-md border border-gray-200 bg-gray-50 px-3 text-sm font-medium text-gray-500">
                                {{ __('No invoice file uploaded') }}
                            </span>
                        @endif
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm sm:px-8 lg:px-10">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Payment') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Complete and audit vendor payment details.') }}</p>

                    @if($vendorInvoice->status === \App\Enums\VendorInvoiceStatus::PAID)
                        <div class="mt-6 space-y-5">
                            <x-detail-item label="Paid Amount" :value="format_money($vendorInvoice->paid_amount)" />
                            <x-detail-item label="Due Amount" :value="format_money($dueAmount)" />
                            <x-detail-item label="Payment Method" :value="$vendorInvoice->payment_method ? Str::headline($vendorInvoice->payment_method) : '-'" />
                            <x-detail-item label="Payment Reference" :value="$vendorInvoice->payment_reference ?: '-'" />
                            <x-detail-item label="Paid At" :value="$vendorInvoice->paid_at?->format('d/m/Y h:i A') ?: '-'" />
                            <x-detail-item label="Paid By" :value="$vendorInvoice->paidBy?->name ?: '-'" />
                            <x-detail-item label="Payment Notes" :value="$vendorInvoice->payment_notes ?: '-'" />
                        </div>
                    @else
                        <form method="POST" action="{{ route('vendor-invoices.mark-paid', $vendorInvoice) }}" class="mt-6 space-y-5">
                            @csrf
                            @method('PATCH')

                            <div class="space-y-2">
                                <x-input-label for="paid_amount" :value="__('Amount Paid')" required hint="Amount paid now. Example: full due amount or partial amount." />
                                <div class="flex items-center gap-3">
                                    <input
                                        id="paid_amount"
                                        type="number"
                                        name="paid_amount"
                                        min="1"
                                        max="{{ $dueAmount }}"
                                        value="{{ old('paid_amount', $dueAmount) }}"
                                        required
                                        class="h-10 w-36 rounded-md border border-gray-300 px-3 text-lg font-bold text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <span class="text-lg font-bold text-gray-900">{{ __('INR') }}</span>
                                </div>
                                <p class="text-xs text-gray-500">{{ __('Cannot be greater than due amount') }}: {{ format_money($dueAmount) }}</p>
                                <x-input-error :messages="$errors->get('paid_amount')" />
                            </div>

                            <div class="space-y-2">
                                <x-input-label for="payment_method" :value="__('Payment Method')" required hint="How this vendor invoice was paid. Example: Bank Transfer." />
                                <select id="payment_method" name="payment_method" required class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="">{{ __('Select method') }}</option>
                                    <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>{{ __('Bank Transfer') }}</option>
                                    <option value="upi" @selected(old('payment_method') === 'upi')>{{ __('UPI') }}</option>
                                    <option value="cheque" @selected(old('payment_method') === 'cheque')>{{ __('Cheque') }}</option>
                                    <option value="cash" @selected(old('payment_method') === 'cash')>{{ __('Cash') }}</option>
                                    <option value="card" @selected(old('payment_method') === 'card')>{{ __('Card') }}</option>
                                    <option value="other" @selected(old('payment_method') === 'other')>{{ __('Other') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('payment_method')" />
                            </div>

                            <div class="space-y-2">
                                <x-input-label for="payment_reference" :value="__('Payment Reference')" hint="Bank UTR, cheque number, UPI transaction ID, or internal reference. Example: UTR123456789." />
                                <x-text-input id="payment_reference" name="payment_reference" :value="old('payment_reference')" placeholder="UTR / cheque / transaction reference" />
                                <x-input-error :messages="$errors->get('payment_reference')" />
                            </div>

                            <div class="space-y-2">
                                <x-input-label for="paid_at" :value="__('Payment Date')" required hint="Date when payment was completed. Example: today." />
                                <x-text-input id="paid_at" type="date" name="paid_at" :value="old('paid_at', now()->toDateString())" required />
                                <x-input-error :messages="$errors->get('paid_at')" />
                            </div>

                            <div class="space-y-2">
                                <x-input-label for="payment_notes" :value="__('Payment Notes')" hint="Any settlement remarks or bank notes. Example: Paid after GST invoice verification." />
                                <textarea id="payment_notes" name="payment_notes" rows="2" class="w-full rounded-md border-gray-300 text-sm" placeholder="Optional payment notes...">{{ old('payment_notes') }}</textarea>
                                <x-input-error :messages="$errors->get('payment_notes')" />
                            </div>

                            <button
                                type="submit"
                                class="mt-2 inline-flex h-11 w-full items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold shadow-sm"
                                style="display:flex !important; visibility:visible !important; opacity:1 !important; background-color:#059669 !important; color:#ffffff !important; border:1px solid #047857 !important;"
                            >
                                <x-heroicon-o-check-circle class="h-4 w-4" />
                                {{ __('Save & Complete Payment') }}
                            </button>
                        </form>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
