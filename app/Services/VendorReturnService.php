<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\VendorInvoiceStatus;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\VendorReturn;
use App\Models\VendorReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorReturnService
{
    /**
     * Generate the next sequential Vendor Return number (e.g. VR-2026-0001).
     */
    public function generateReturnNumber(): string
    {
        $year = date('Y');
        $prefix = "VR-{$year}-";

        $lastReturn = VendorReturn::where('return_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($lastReturn && preg_match('/VR-\d{4}-(\d+)/', $lastReturn->return_number, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $nextSeq = 1;
        }

        return sprintf('VR-%s-%04d', $year, $nextSeq);
    }

    /**
     * Create and process a Vendor Return.
     *
     * @param  array{
     *     purchase_id: int,
     *     return_date?: string,
     *     reason?: string,
     *     notes?: string,
     *     items: array<int, array{purchase_item_id: int, quantity: int, reason?: string}>
     * }  $data
     */
    public function createReturn(array $data, int $userId): VendorReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $purchase = Purchase::with(['supplier', 'company', 'vendorInvoice', 'items.product'])
                ->lockForUpdate()
                ->findOrFail($data['purchase_id']);

            if ($purchase->status !== PurchaseStatus::RECEIVED && $purchase->status !== PurchaseStatus::RECEIVED->value) {
                throw new \InvalidArgumentException('Vendor returns can only be processed on received purchase orders.');
            }

            $itemsData = array_filter($data['items'] ?? [], function ($item) {
                return ! empty($item['purchase_item_id']) && (int) ($item['quantity'] ?? 0) > 0;
            });

            if (empty($itemsData)) {
                throw new \InvalidArgumentException('Please select at least one item with a valid return quantity.');
            }

            $returnNumber = $this->generateReturnNumber();
            $returnDate = $data['return_date'] ?? now()->toDateString();
            $globalReason = $data['reason'] ?? 'damaged';
            $location = $purchase->location?->value ?? 'shop';

            $vendorReturn = VendorReturn::create([
                'return_number' => $returnNumber,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'company_id' => $purchase->company_id,
                'location' => $location,
                'return_date' => $returnDate,
                'total_amount' => 0,
                'status' => 'completed',
                'reason' => $globalReason,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $totalReturnAmount = 0.0;

            foreach ($itemsData as $entry) {
                $purchaseItemId = (int) $entry['purchase_item_id'];
                $returnQty = (int) $entry['quantity'];
                $itemReason = $entry['reason'] ?? $globalReason;

                /** @var PurchaseItem $purchaseItem */
                $purchaseItem = PurchaseItem::where('id', $purchaseItemId)
                    ->where('purchase_id', $purchase->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $product = Product::where('id', $purchaseItem->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $availableBatchStock = (int) ($purchaseItem->received_quantity ?? 0);
                if ($returnQty > $availableBatchStock) {
                    throw new \InvalidArgumentException(
                        "Cannot return {$returnQty} units of \"{$product->name}\". Only {$availableBatchStock} units available in batch {$purchaseItem->batch_number}."
                    );
                }

                if ($product->quantity < $returnQty) {
                    throw new \InvalidArgumentException(
                        "Cannot return {$returnQty} units of \"{$product->name}\". Current total inventory stock is {$product->quantity}."
                    );
                }

                $unitPrice = (float) $purchaseItem->unit_price;
                $lineSubtotal = round($unitPrice * $returnQty, 2);
                $totalReturnAmount += $lineSubtotal;

                // 1. Decrement batch received_quantity
                $purchaseItem->decrement('received_quantity', $returnQty);

                // 2. Decrement overall product quantity
                $product->decrement('quantity', $returnQty);

                // 3. Decrement InventoryStock
                $stock = InventoryStock::where('product_id', $product->id)->lockForUpdate()->first();
                if ($stock) {
                    $stock->decrement('quantity', min($stock->quantity, $returnQty));
                }

                // 4. Record InventoryMovement
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'purchase_id' => $purchase->id,
                    'purchase_item_id' => $purchaseItem->id,
                    'type' => 'vendor_return',
                    'location' => $purchaseItem->location?->value ?? $purchase->location?->value ?? 'shop',
                    'quantity' => -$returnQty,
                    'balance_after' => $product->fresh()->quantity,
                    'reference' => $returnNumber,
                    'notes' => "Vendor return: {$vendorReturn->reason_label} for PO #{$purchase->invoice_number}. Batch: {$purchaseItem->batch_number}",
                ]);

                // 5. Create VendorReturnItem
                VendorReturnItem::create([
                    'vendor_return_id' => $vendorReturn->id,
                    'purchase_item_id' => $purchaseItem->id,
                    'product_id' => $product->id,
                    'batch_number' => $purchaseItem->batch_number,
                    'quantity' => $returnQty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                    'reason' => $itemReason,
                ]);
            }

            $vendorReturn->update([
                'total_amount' => round($totalReturnAmount, 2),
            ]);

            // Adjust Vendor Invoice if one exists
            $vendorInvoice = $purchase->vendorInvoice;
            if ($vendorInvoice) {
                $oldAmount = (float) $vendorInvoice->amount;
                $newAmount = max(0, round($oldAmount - $totalReturnAmount, 2));

                $existingNotes = $vendorInvoice->payment_notes ?? '';
                $returnNote = "[Vendor Return {$returnNumber}: -".format_money($totalReturnAmount).']';
                $updatedNotes = trim($existingNotes ? "{$existingNotes}\n{$returnNote}" : $returnNote);

                $updateData = [
                    'amount' => $newAmount,
                    'payment_notes' => $updatedNotes,
                ];

                if ($vendorInvoice->status !== VendorInvoiceStatus::PAID) {
                    if ((float) $vendorInvoice->paid_amount >= $newAmount && $newAmount > 0) {
                        $updateData['status'] = VendorInvoiceStatus::PAID;
                    } elseif ($newAmount == 0) {
                        $updateData['status'] = VendorInvoiceStatus::PAID;
                    }
                }

                $vendorInvoice->update($updateData);
            }

            Log::info("[VendorReturnService] Processed Vendor Return {$returnNumber} for PO #{$purchase->id}. Total value: {$totalReturnAmount}.");

            return $vendorReturn->load(['supplier', 'company', 'purchase', 'items.product', 'creator']);
        });
    }
}
