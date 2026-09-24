<?php

namespace App\Services;

use App\Enums\VendorInvoiceStatus;
use App\Models\Purchase;
use App\Models\VendorInvoice;
use Illuminate\Support\Facades\Auth;

class VendorInvoiceService
{
    public function createFromPurchase(
        Purchase $purchase,
        ?string $invoiceNumber,
        ?string $documentPath,
        ?string $orderReceivedDate = null,
        float $vendorAdditionalDiscount = 0
    ): VendorInvoice
    {
        $receivedTotal = $purchase->items->sum(function ($item) {
            return ((int) ($item->received_quantity ?? $item->quantity)) * ((float) $item->unit_price);
        });
        $amount = max(0, (float) $receivedTotal - $vendorAdditionalDiscount);

        return VendorInvoice::updateOrCreate(
            ['purchase_id' => $purchase->id],
            [
                'supplier_id' => $purchase->supplier_id,
                'company_id' => $purchase->company_id,
                'po_reference' => $purchase->invoice_number,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now()->toDateString(),
                'order_received_date' => $orderReceivedDate ?: now()->toDateString(),
                'amount' => $amount,
                'vendor_additional_discount' => $vendorAdditionalDiscount,
                'document_path' => $documentPath ?: VendorInvoice::where('purchase_id', $purchase->id)->value('document_path'),
                'status' => VendorInvoiceStatus::UNPAID,
            ]
        );
    }

    public function markPaid(VendorInvoice $vendorInvoice, array $paymentDetails = []): void
    {
        if ($vendorInvoice->status === VendorInvoiceStatus::PAID) {
            return;
        }

        $paidAmount = (float) ($paymentDetails['paid_amount'] ?? 0);
        $newPaidAmount = min((float) $vendorInvoice->amount, (float) $vendorInvoice->paid_amount + $paidAmount);
        $status = $newPaidAmount >= (float) $vendorInvoice->amount
            ? VendorInvoiceStatus::PAID
            : VendorInvoiceStatus::PARTIALLY_PAID;

        $vendorInvoice->update([
            'status' => $status,
            'paid_amount' => $newPaidAmount,
            'payment_method' => $paymentDetails['payment_method'] ?? null,
            'payment_reference' => $paymentDetails['payment_reference'] ?? null,
            'payment_notes' => $paymentDetails['payment_notes'] ?? null,
            'paid_at' => $paymentDetails['paid_at'] ?? now(),
            'paid_by' => Auth::id(),
        ]);
    }
}
