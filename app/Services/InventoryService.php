<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;

class InventoryService
{
    public function receivePurchaseItem(Purchase $purchase, PurchaseItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $stock = InventoryStock::query()
            ->where('product_id', $item->product_id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = InventoryStock::create([
                'product_id' => $item->product_id,
                'quantity' => 0,
            ]);
        }

        $stock->increment('quantity', $quantity);
        $stock->refresh();

        InventoryMovement::create([
            'product_id' => $item->product_id,
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $item->id,
            'type' => 'purchase_receive',
            'quantity' => $quantity,
            'balance_after' => $stock->quantity,
            'reference' => $purchase->invoice_number,
            'notes' => 'Received from purchase order.',
        ]);
    }

    /**
     * Adjust inventory stock for a sale-related event (sale, cancellation, restore).
     * Positive $quantityDelta increases stock, negative decreases it.
     */
    public function adjustForSale(int $productId, int $quantityDelta, string $type, ?string $reference = null, ?string $notes = null, ?Sale $sale = null, ?SaleItem $saleItem = null): void
    {
        if ($quantityDelta === 0) {
            return;
        }

        $stock = InventoryStock::query()
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = InventoryStock::create([
                'product_id' => $productId,
                'quantity' => 0,
            ]);
        }

        $stock->increment('quantity', $quantityDelta);
        $stock->refresh();

        InventoryMovement::create([
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantityDelta,
            'balance_after' => $stock->quantity,
            'reference' => $reference,
            'notes' => $notes,
            'sale_id' => $sale?->id,
            'sale_item_id' => $saleItem?->id,
        ]);
    }

    /**
     * FIFO-consume received batches (purchase items) for a sold quantity, decrementing
     * each batch's remaining received_quantity and recording a per-batch movement.
     */
    public function deductFromBatches(int $productId, int $quantity, string $type, ?string $reference = null, ?string $notes = null, ?Sale $sale = null, ?SaleItem $saleItem = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        $remaining = $quantity;

        $batches = PurchaseItem::query()
            ->where('product_id', $productId)
            ->where('received_quantity', '>', 0)
            ->whereHas('purchase')
            ->orderBy(
                Purchase::query()
                    ->select('purchase_date')
                    ->whereColumn('purchases.id', 'purchase_items.purchase_id')
                    ->limit(1)
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $batch->received_quantity);

            if ($take <= 0) {
                continue;
            }

            $batch->decrement('received_quantity', $take);
            $batch->refresh();

            InventoryMovement::create([
                'product_id' => $productId,
                'purchase_id' => $batch->purchase_id,
                'purchase_item_id' => $batch->id,
                'type' => $type,
                'quantity' => -$take,
                'balance_after' => $batch->received_quantity,
                'reference' => $reference,
                'notes' => $notes,
                'sale_id' => $sale?->id,
                'sale_item_id' => $saleItem?->id,
            ]);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new \RuntimeException("Insufficient FIFO batch stock for product {$productId}. Missing {$remaining} unit(s).");
        }
    }

    /**
     * Reverse batch deductions previously recorded under $originalType for the given
     * reference (e.g. a sale invoice number), restoring received_quantity per batch.
     */
    public function restoreBatchesForReference(int $productId, string $reference, string|array $originalType, string $type, ?string $notes = null, ?Sale $sale = null, ?SaleItem $saleItem = null, mixed $after = null): void
    {
        $movements = InventoryMovement::query()
            ->where('product_id', $productId)
            ->where('reference', $reference)
            ->when($sale, fn ($query) => $query->where('sale_id', $sale->id))
            ->when($saleItem, fn ($query) => $query->where('sale_item_id', $saleItem->id))
            ->when($after, fn ($query) => $query->where('created_at', '>', $after))
            ->whereIn('type', (array) $originalType)
            ->whereNotNull('purchase_item_id')
            ->get();

        foreach ($movements as $movement) {
            $batch = PurchaseItem::query()->where('id', $movement->purchase_item_id)->lockForUpdate()->first();

            if (! $batch) {
                continue;
            }

            $restoreQty = abs($movement->quantity);
            $batch->increment('received_quantity', $restoreQty);
            $batch->refresh();

            InventoryMovement::create([
                'product_id' => $productId,
                'purchase_id' => $batch->purchase_id,
                'purchase_item_id' => $batch->id,
                'type' => $type,
                'quantity' => $restoreQty,
                'balance_after' => $batch->received_quantity,
                'reference' => $reference,
                'notes' => $notes,
                'sale_id' => $sale?->id ?? $movement->sale_id,
                'sale_item_id' => $movement->sale_item_id,
            ]);
        }
    }
}
