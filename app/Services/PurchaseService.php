<?php

namespace App\Services;

use App\DTOs\PurchaseData;
use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseException;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        protected FinanceTransactionService $financeService,
        protected InventoryService $inventoryService
    ) {}

    public function createPurchase(PurchaseData $data, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            try {
                $purchase = Purchase::create([
                    'invoice_number' => $this->generatePurchaseOrderReference($data->purchase_date),
                    'supplier_id' => $data->supplier_id,
                    'company_id' => $data->company_id,
                    'purchase_date' => $data->purchase_date,
                    'due_date' => $data->due_date,
                    'status' => $data->status,
                    'notes' => $data->notes,
                    'proof_image' => $data->proof_image,
                    'created_by' => $userId,
                    'total' => 0,
                ]);

                $this->syncItems($purchase, $data->items);

                return $purchase;

            } catch (Exception $e) {
                throw PurchaseException::creationFailed($e->getMessage(), ['supplier_id' => $data->supplier_id]);
            }
        });
    }

    public function updatePurchase(Purchase $purchase, PurchaseData $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            try {
                if (! in_array($purchase->status, [PurchaseStatus::DRAFT, PurchaseStatus::ORDERED])) {
                    throw PurchaseException::invalidStatus('update', $purchase->status->label(), ['id' => $purchase->id]);
                }

                $purchase->update([
                    'supplier_id' => $data->supplier_id,
                    'company_id' => $data->company_id,
                    'purchase_date' => $data->purchase_date,
                    'due_date' => $data->due_date,
                    'notes' => $data->notes,
                    'proof_image' => $data->proof_image,
                ]);

                // Full sync of items
                $purchase->items()->delete();
                $this->syncItems($purchase, $data->items);

                return $purchase->refresh();

            } catch (Exception $e) {
                if ($e instanceof PurchaseException) {
                    throw $e;
                }
                throw PurchaseException::updateFailed($e->getMessage(), ['id' => $purchase->id]);
            }
        });
    }

    public function deletePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            try {
                if (! in_array($purchase->status, [PurchaseStatus::DRAFT, PurchaseStatus::CANCELLED])) {
                    throw PurchaseException::deletionFailed(
                        "Cannot delete purchase with status [{$purchase->status->label()}]. Only Draft or Cancelled purchases can be deleted.",
                        ['id' => $purchase->id, 'status' => $purchase->status->value]
                    );
                }

                $this->financeService->voidTransaction($purchase);

                $purchase->items()->delete();
                $purchase->delete();

            } catch (Exception $e) {
                if ($e instanceof PurchaseException) {
                    throw $e;
                }
                throw PurchaseException::deletionFailed($e->getMessage(), ['id' => $purchase->id]);
            }
        });
    }

    public function markAsOrdered(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            if ($purchase->status !== PurchaseStatus::DRAFT) {
                throw PurchaseException::invalidStatus('order', $purchase->status->label(), ['id' => $purchase->id]);
            }

            if ($purchase->items()->count() === 0) {
                throw PurchaseException::updateFailed('Cannot order a purchase with no items.', ['id' => $purchase->id]);
            }

            $purchase->update(['status' => PurchaseStatus::ORDERED]);
        });
    }

    public function markAsReceived(Purchase $purchase, array $receivedQuantities = [], array $itemReceiptDates = []): void
    {
        DB::transaction(function () use ($purchase, $receivedQuantities, $itemReceiptDates) {
            if (! in_array($purchase->status, [PurchaseStatus::ORDERED, PurchaseStatus::DRAFT])) {
                throw PurchaseException::invalidStatus('receive', $purchase->status->label(), ['id' => $purchase->id]);
            }

            if (empty($purchase->invoice_number)) {
                throw PurchaseException::missingReference('PO Reference', ['id' => $purchase->id]);
            }

            // Update Stock
            foreach ($purchase->items as $item) {
                $receivedQuantity = array_key_exists($item->id, $receivedQuantities)
                    ? (int) $receivedQuantities[$item->id]
                    : (int) $item->quantity;

                if ($receivedQuantity < 0) {
                    throw PurchaseException::updateFailed('Received quantity cannot be negative.', ['item_id' => $item->id]);
                }

                $receiptDate = $itemReceiptDates[$item->id]['order_received_date'] ?? now()->toDateString();
                $batchNumber = $receivedQuantity > 0
                    ? ($item->batch_number ?: $this->generateBatchNumber(Carbon::parse($receiptDate)))
                    : null;
                $updatedUnitPrice = isset($itemReceiptDates[$item->id]['unit_price'])
                    ? (float) $itemReceiptDates[$item->id]['unit_price']
                    : (float) $item->unit_price;
                $updatedSellingPrice = isset($itemReceiptDates[$item->id]['selling_price'])
                    ? (float) $itemReceiptDates[$item->id]['selling_price']
                    : (float) $item->selling_price;

                $item->update([
                    'received_quantity' => $receivedQuantity,
                    'batch_number' => $batchNumber,
                    'manufacturing_date' => $itemReceiptDates[$item->id]['manufacturing_date'] ?? null,
                    'expiry_date' => $itemReceiptDates[$item->id]['expiry_date'] ?? null,
                    'unit_price' => $updatedUnitPrice,
                    'selling_price' => $updatedSellingPrice,
                    'subtotal' => number_format(((int) $item->quantity) * $updatedUnitPrice, 2, '.', ''),
                ]);
                $item->refresh();

                // Lock the product row for update to prevent race conditions
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

                if ($product) {
                    $product->increment('quantity', $receivedQuantity);
                    $this->inventoryService->receivePurchaseItem($purchase, $item, $receivedQuantity);

                    // Update latest purchase price and selling price
                    $updateData = ['purchase_price' => $item->unit_price];
                    $priceChangeLog = '';
                    $hasPriceChange = false;
                    $ref = $purchase->invoice_number ? "PO #{$purchase->invoice_number}" : "Purchase #{$purchase->id}";
                    $newProductMrp = isset($itemReceiptDates[$item->id]['product_mrp'])
                        ? (float) $itemReceiptDates[$item->id]['product_mrp']
                        : null;

                    if ($newProductMrp !== null && (float) $product->mrp !== $newProductMrp) {
                        $oldMrp = (float) $product->mrp;
                        $updateData['mrp'] = $newProductMrp;
                        $product->priceHistories()->create([
                            'changed_by' => Auth::id(),
                            'source' => 'purchase_receive',
                            'reference' => $ref,
                            'old_mrp' => $oldMrp,
                            'new_mrp' => $newProductMrp,
                            'notes' => 'MRP updated while receiving purchase order.',
                        ]);
                    }

                    // Check for Purchase Price Change
                    if ((float) $product->purchase_price !== (float) $item->unit_price) {
                        $hasPriceChange = true;
                        $oldBuy = format_money($product->purchase_price ?? 0);
                        $newBuy = format_money($item->unit_price);
                        $priceChangeLog .= "\n- Buying Price: {$oldBuy} -> {$newBuy}";
                    }

                    // Check for Selling Price Change
                    if ($item->selling_price) {
                        $updateData['selling_price'] = $item->selling_price;
                        if ((float) $product->selling_price !== (float) $item->selling_price) {
                            $hasPriceChange = true;
                            $oldSell = format_money($product->selling_price ?? 0);
                            $newSell = format_money($item->selling_price);
                            $priceChangeLog .= "\n- Selling Price: {$oldSell} -> {$newSell}";
                        }
                    }

                    // Append to Notes if prices changed
                    if ($hasPriceChange) {
                        $timestamp = now()->format('Y-m-d H:i');
                        $logHeader = "\n\n[System Log - {$timestamp}] Price update via {$ref}:";
                        $updateData['notes'] = trim(($product->notes ?? '').$logHeader.$priceChangeLog);

                    }

                    $product->update($updateData);
                }
            }

            $purchase->update([
                'status' => PurchaseStatus::RECEIVED,
                'total' => $purchase->items()->sum('subtotal'),
            ]);
        });
    }

    public function markAsPaid(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            if (! in_array($purchase->status, [PurchaseStatus::ORDERED, PurchaseStatus::RECEIVED])) {
                throw PurchaseException::invalidStatus('pay', $purchase->status->label(), ['id' => $purchase->id]);
            }

            // Strict Validation for Payment
            if (empty($purchase->invoice_number)) {
                throw PurchaseException::missingReference('PO Reference', ['id' => $purchase->id]);
            }

            $purchase->update(['status' => PurchaseStatus::PAID]);

            $this->financeService->recordExpenseFromPurchase($purchase);
        });
    }

    public function cancelPurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            if ($purchase->status === PurchaseStatus::RECEIVED || $purchase->status === PurchaseStatus::PAID) {
                throw PurchaseException::invalidStatus('cancel', $purchase->status->label(), ['id' => $purchase->id]);
            }

            $purchase->update(['status' => PurchaseStatus::CANCELLED]);

            $this->financeService->voidTransaction($purchase);
        });
    }

    public function restoreToDraft(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            if ($purchase->status !== PurchaseStatus::CANCELLED) {
                throw PurchaseException::invalidStatus('restore', $purchase->status->label(), ['id' => $purchase->id]);
            }

            $purchase->update(['status' => PurchaseStatus::DRAFT]);
        });
    }

    private function syncItems(Purchase $purchase, array $items): void
    {
        $total = 0;

        foreach ($items as $itemData) {
            $subtotal = $itemData->quantity * $itemData->unit_price;

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $itemData->product_id,
                'quantity' => $itemData->quantity,
                'unit_price' => $itemData->unit_price,
                'subtotal' => $subtotal,
                'selling_price' => $itemData->selling_price,
            ]);

            $total += $subtotal;
        }

        $purchase->update(['total' => $total]);
    }

    public function previewPurchaseOrderReference(?Carbon $purchaseDate = null): string
    {
        return $this->generatePurchaseOrderReference($purchaseDate, false);
    }

    private function generatePurchaseOrderReference(?Carbon $purchaseDate = null, bool $lock = true): string
    {
        $prefix = 'PO-'.($purchaseDate ?? now())->format('Ymd').'-';
        $query = Purchase::query()
            ->where('invoice_number', 'like', $prefix.'%');

        if ($lock) {
            $query->lockForUpdate();
        }

        $lastReference = $query->orderByDesc('invoice_number')->value('invoice_number');

        $nextNumber = $lastReference
            ? ((int) substr($lastReference, -4)) + 1
            : 1;

        do {
            $reference = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Purchase::query()->where('invoice_number', $reference)->exists());

        return $reference;
    }

    private function generateBatchNumber(?Carbon $batchDate = null, bool $lock = true): string
    {
        $prefix = 'BN-'.($batchDate ?? now())->format('Ymd').'-';
        $query = PurchaseItem::query()
            ->where('batch_number', 'like', $prefix.'%');

        if ($lock) {
            $query->lockForUpdate();
        }

        $lastBatchNumber = $query->orderByDesc('batch_number')->value('batch_number');

        $nextNumber = $lastBatchNumber
            ? ((int) substr($lastBatchNumber, -4)) + 1
            : 1;

        do {
            $batchNumber = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (PurchaseItem::query()->where('batch_number', $batchNumber)->exists());

        return $batchNumber;
    }
}
