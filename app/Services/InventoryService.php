<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
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
            'location' => $item->location?->value ?? $purchase->location?->value ?? 'shop',
            'quantity' => $quantity,
            'balance_after' => $stock->quantity,
            'reference' => $purchase->invoice_number,
            'notes' => 'Received from purchase order.',
        ]);
    }

    /**
     * Adjust inventory stock when a received purchase order's quantity is edited.
     * Positive $quantityDelta increases stock, negative decreases it.
     */
    public function adjustPurchaseItemStock(Purchase $purchase, PurchaseItem $item, int $quantityDelta): void
    {
        if ($quantityDelta === 0) {
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

        $stock->increment('quantity', $quantityDelta);
        $stock->refresh();

        InventoryMovement::create([
            'product_id' => $item->product_id,
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $item->id,
            'type' => 'purchase_adjustment',
            'location' => $item->location?->value ?? $purchase->location?->value ?? 'shop',
            'quantity' => $quantityDelta,
            'balance_after' => $stock->quantity,
            'reference' => $purchase->invoice_number,
            'notes' => $quantityDelta > 0
                ? "PO adjustment: received quantity increased by {$quantityDelta}."
                : 'PO adjustment: received quantity reduced by '.abs($quantityDelta).'.',
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
    public function deductFromBatches(int $productId, int $quantity, string $type, ?string $reference = null, ?string $notes = null, ?Sale $sale = null, ?SaleItem $saleItem = null, ?string $location = 'shop'): void
    {
        if ($quantity <= 0) {
            return;
        }

        $remaining = $quantity;

        $batches = PurchaseItem::query()
            ->where('product_id', $productId)
            ->where('received_quantity', '>', 0)
            ->when($location !== null, function ($q) use ($location) {
                $q->where(function ($sub) use ($location) {
                    $sub->where('location', $location);
                    if ($location === 'shop') {
                        $sub->orWhereNull('location');
                    }
                });
            })
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
                'location' => $batch->location?->value ?? ($batch->location ?: 'shop'),
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
                'location' => $batch->location?->value ?? ($batch->location ?: 'shop'),
                'quantity' => $restoreQty,
                'balance_after' => $batch->received_quantity,
                'reference' => $reference,
                'notes' => $notes,
                'sale_id' => $sale?->id ?? $movement->sale_id,
                'sale_item_id' => $movement->sale_item_id,
            ]);
        }
    }

    /**
     * Restore product units back to their original batch from a previously sold SaleItem.
     */
    public function returnSaleItemToBatch(SaleItem $originalSaleItem, int $returnQuantity, Sale $newSale, string $type = 'sale_return'): void
    {
        if ($returnQuantity <= 0 || ! $originalSaleItem->product_id) {
            return;
        }

        $productId = $originalSaleItem->product_id;
        $product = Product::query()->lockForUpdate()->find($productId);
        if ($product) {
            $product->increment('quantity', $returnQuantity);
        }

        $stock = InventoryStock::query()->where('product_id', $productId)->lockForUpdate()->first();
        if (! $stock) {
            $stock = InventoryStock::create([
                'product_id' => $productId,
                'quantity' => 0,
            ]);
        }
        $stock->increment('quantity', $returnQuantity);
        $stock->refresh();

        // Create product-level movement
        $originalInvoice = $originalSaleItem->sale?->invoice_number ?? 'N/A';
        InventoryMovement::create([
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $returnQuantity,
            'balance_after' => $stock->quantity,
            'reference' => $newSale->invoice_number,
            'notes' => "Customer return of {$returnQuantity} unit(s) from invoice {$originalInvoice}",
            'sale_id' => $newSale->id,
            'sale_item_id' => $originalSaleItem->id,
        ]);

        // Find batch movements previously recorded for this original sale item
        $batchMovements = InventoryMovement::query()
            ->where('sale_item_id', $originalSaleItem->id)
            ->whereNotNull('purchase_item_id')
            ->where('quantity', '<', 0)
            ->orderBy('id', 'desc')
            ->get();

        $remainingToRestore = $returnQuantity;

        foreach ($batchMovements as $bm) {
            if ($remainingToRestore <= 0) {
                break;
            }

            $batch = PurchaseItem::query()->where('id', $bm->purchase_item_id)->lockForUpdate()->first();
            if (! $batch) {
                continue;
            }

            // Check how many units have already been restored for this batch on this sale item
            $alreadyRestored = (int) InventoryMovement::query()
                ->where('sale_item_id', $originalSaleItem->id)
                ->where('purchase_item_id', $bm->purchase_item_id)
                ->where('type', 'sale_return')
                ->sum('quantity');

            $availableToRestore = max(0, abs($bm->quantity) - $alreadyRestored);
            $restore = min($remainingToRestore, $availableToRestore);

            if ($restore <= 0) {
                continue;
            }

            $batch->increment('received_quantity', $restore);
            $batch->refresh();

            InventoryMovement::create([
                'product_id' => $productId,
                'purchase_id' => $batch->purchase_id,
                'purchase_item_id' => $batch->id,
                'type' => $type,
                'location' => $batch->location?->value ?? ($batch->location ?: 'shop'),
                'quantity' => $restore,
                'balance_after' => $batch->received_quantity,
                'reference' => $newSale->invoice_number,
                'notes' => "Customer return of {$restore} unit(s) from invoice {$originalInvoice} to batch {$batch->batch_number}",
                'sale_id' => $newSale->id,
                'sale_item_id' => $originalSaleItem->id,
            ]);

            $remainingToRestore -= $restore;
        }

        // Fallback: If no batch movements were tracked on this sale item (e.g. historical sales)
        // restore to the latest available batch for this product
        if ($remainingToRestore > 0) {
            $fallbackBatch = PurchaseItem::query()
                ->where('product_id', $productId)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            if ($fallbackBatch) {
                $fallbackBatch->increment('received_quantity', $remainingToRestore);
                $fallbackBatch->refresh();

                InventoryMovement::create([
                    'product_id' => $productId,
                    'purchase_id' => $fallbackBatch->purchase_id,
                    'purchase_item_id' => $fallbackBatch->id,
                    'type' => $type,
                    'location' => $fallbackBatch->location?->value ?? ($fallbackBatch->location ?: 'shop'),
                    'quantity' => $remainingToRestore,
                    'balance_after' => $fallbackBatch->received_quantity,
                    'reference' => $newSale->invoice_number,
                    'notes' => "Customer return of {$remainingToRestore} unit(s) from invoice {$originalInvoice} to batch {$fallbackBatch->batch_number}",
                    'sale_id' => $newSale->id,
                    'sale_item_id' => $originalSaleItem->id,
                ]);
            }
        }
    }
}
