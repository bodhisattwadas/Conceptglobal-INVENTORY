<?php

namespace App\Http\Controllers;

use App\Models\VendorInvoice;
use App\Services\VendorInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorInvoiceController extends Controller
{
    public function index()
    {
        return view('vendor-invoices.index');
    }

    public function show(VendorInvoice $vendorInvoice)
    {
        $vendorInvoice->load(['purchase', 'supplier', 'company', 'paidBy']);

        return view('vendor-invoices.show', compact('vendorInvoice'));
    }

    public function update(Request $request, VendorInvoice $vendorInvoice, VendorInvoiceService $service)
    {
        $validated = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'vendor_additional_discount' => ['nullable', 'numeric', 'min:0'],
            'additional_discount' => ['nullable', 'numeric', 'min:0'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $service->updateInvoiceDetails(
            $vendorInvoice,
            $validated,
            $request->file('document')
        );

        return redirect()
            ->route('vendor-invoices.show', $vendorInvoice)
            ->with('success', 'Vendor invoice details updated successfully.');
    }

    public function markPaid(Request $request, VendorInvoice $vendorInvoice, VendorInvoiceService $service)
    {
        $dueAmount = max(0, (float) $vendorInvoice->amount - (float) $vendorInvoice->paid_amount);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'upi', 'cheque', 'card', 'other'])],
            'paid_amount' => ['required', 'numeric', 'min:0.01', 'max:'.$dueAmount],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['required', 'date'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'paid_amount.max' => 'Paid amount cannot be greater than the total due amount.',
        ]);

        $service->markPaid($vendorInvoice, $validated);

        return redirect()
            ->route('vendor-invoices.show', $vendorInvoice)
            ->with('success', 'Vendor invoice payment completed.');
    }
}
