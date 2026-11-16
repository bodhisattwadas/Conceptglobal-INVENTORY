<?php

namespace App\Services;

use App\Enums\VendorInvoiceStatus;
use App\Models\Purchase;
use App\Models\VendorInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VendorInvoiceService
{
    public function createFromPurchase(
        Purchase $purchase,
        ?string $invoiceNumber,
        ?string $documentPath,
        ?string $orderReceivedDate = null,
        float $vendorAdditionalDiscount = 0
    ): VendorInvoice {
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

    public function updateInvoiceDetails(
        VendorInvoice $vendorInvoice,
        array $data,
        ?UploadedFile $document = null
    ): VendorInvoice {
        $updateData = [];

        if (array_key_exists('invoice_number', $data)) {
            $updateData['invoice_number'] = $data['invoice_number'] !== null && trim((string) $data['invoice_number']) !== ''
                ? trim((string) $data['invoice_number'])
                : null;
        }

        if (array_key_exists('invoice_date', $data)) {
            $updateData['invoice_date'] = $data['invoice_date'] ?: null;
        }

        $hasDiscount = false;
        $newDiscount = null;
        if (array_key_exists('vendor_additional_discount', $data) && $data['vendor_additional_discount'] !== null) {
            $hasDiscount = true;
            $newDiscount = (float) $data['vendor_additional_discount'];
        } elseif (array_key_exists('additional_discount', $data) && $data['additional_discount'] !== null) {
            $hasDiscount = true;
            $newDiscount = (float) $data['additional_discount'];
        }

        if ($hasDiscount) {
            $updateData['vendor_additional_discount'] = $newDiscount;

            $purchase = $vendorInvoice->purchase()->with('items')->first();
            if ($purchase && $purchase->items->isNotEmpty()) {
                $receivedTotal = $purchase->items->sum(function ($item) {
                    return ((int) ($item->received_quantity ?? $item->quantity)) * ((float) $item->unit_price);
                });
                $newAmount = max(0, (float) $receivedTotal - $newDiscount);
                $updateData['amount'] = $newAmount;

                $paidAmount = (float) $vendorInvoice->paid_amount;
                if ($paidAmount >= $newAmount && $newAmount > 0) {
                    $updateData['status'] = VendorInvoiceStatus::PAID;
                } elseif ($paidAmount > 0 && $paidAmount < $newAmount) {
                    $updateData['status'] = VendorInvoiceStatus::PARTIALLY_PAID;
                } elseif ($paidAmount <= 0) {
                    $updateData['status'] = VendorInvoiceStatus::UNPAID;
                }
            }
        }

        $fileToUpload = $document ?? ($data['document'] ?? null);
        if ($fileToUpload instanceof UploadedFile) {
            if ($vendorInvoice->document_path && Storage::disk('public')->exists($vendorInvoice->document_path)) {
                Storage::disk('public')->delete($vendorInvoice->document_path);
            }
            $updateData['document_path'] = $fileToUpload->store('vendor-invoices', 'public');
        }

        if (! empty($updateData)) {
            $vendorInvoice->update($updateData);
        }

        return $vendorInvoice->refresh();
    }
}
