<?php

namespace App\Services;

use App\Enums\PurchaseLocation;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class InventoryAdjustmentService
{
    /**
     * Reallocate / split stock from a source product's batch to a target product.
     */
    public function reallocateStock(array $data, int $userId): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            $sourceProductId = (int) ($data['source_product_id'] ?? 0);
            $sourceBatchId = (int) ($data['source_purchase_item_id'] ?? 0);
            $targetProductId = (int) ($data['target_product_id'] ?? 0);
            $quantity = (int) ($data['quantity'] ?? 0);
            $reason = trim($data['reason'] ?? 'misallocation_split');
            $notes = trim($data['notes'] ?? '');

            if ($quantity <= 0) {
                throw new InvalidArgumentException('Quantity must be greater than zero.');
            }

            if ($sourceProductId === $targetProductId) {
                throw new InvalidArgumentException('Source and target products cannot be the same.');
            }

            // Lock source entities
            $sourceProduct = Product::where('id', $sourceProductId)->lockForUpdate()->first();
            if (! $sourceProduct) {
                throw new RuntimeException("Source product #{$sourceProductId} not found.");
            }

            $sourceStock = InventoryStock::firstOrCreate(
                ['product_id' => $sourceProductId],
                ['quantity' => $sourceProduct->quantity ?? 0]
            );
            $sourceStock = InventoryStock::where('id', $sourceStock->id)->lockForUpdate()->first();

            $sourceBatch = PurchaseItem::where('id', $sourceBatchId)
                ->where('product_id', $sourceProductId)
                ->lockForUpdate()
                ->first();

            if (! $sourceBatch) {
                throw new RuntimeException("Source batch #{$sourceBatchId} not found for product #{$sourceProductId}.");
            }

            if ((int) $sourceBatch->received_quantity < $quantity) {
                throw new RuntimeException(
                    "Insufficient batch stock. Available: {$sourceBatch->received_quantity} unit(s), requested: {$quantity} unit(s)."
                );
            }

            if ((int) $sourceProduct->quantity < $quantity) {
                throw new RuntimeException(
                    "Insufficient product stock. Available: {$sourceProduct->quantity} unit(s), requested: {$quantity} unit(s)."
                );
            }

            // Lock target entities
            $targetProduct = Product::where('id', $targetProductId)->lockForUpdate()->first();
            if (! $targetProduct) {
                throw new RuntimeException("Target product #{$targetProductId} not found.");
            }

            $targetStock = InventoryStock::firstOrCreate(
                ['product_id' => $targetProductId],
                ['quantity' => $targetProduct->quantity ?? 0]
            );
            $targetStock = InventoryStock::where('id', $targetStock->id)->lockForUpdate()->first();

            $adjustmentNumber = InventoryAdjustment::generateAdjustmentNumber();

            // 1. Deduct from Source Product X
            $sourceProduct->decrement('quantity', $quantity);
            $sourceStock->decrement('quantity', $quantity);
            $sourceBatch->decrement('received_quantity', $quantity);

            $sourceStock->refresh();
            $sourceProduct->refresh();
            $sourceBatch->refresh();

            InventoryMovement::create([
                'product_id' => $sourceProductId,
                'purchase_id' => $sourceBatch->purchase_id,
                'purchase_item_id' => $sourceBatch->id,
                'type' => 'reallocation_out',
                'quantity' => -$quantity,
                'balance_after' => $sourceStock->quantity,
                'reference' => $adjustmentNumber,
                'notes' => "Reallocated {$quantity} unit(s) to {$targetProduct->name}. Reason: {$reason}.".($notes ? " Notes: {$notes}" : ''),
            ]);

            // 2. Add to Target Product Y
            if (! empty($data['target_mrp']) && (float) $data['target_mrp'] > 0) {
                $targetProduct->mrp = (float) $data['target_mrp'];
                $targetProduct->selling_price = ! empty($data['target_selling_price'])
                    ? (float) $data['target_selling_price']
                    : (float) $data['target_mrp'];
            }

            if (! $targetProduct->purchase_price || (float) $targetProduct->purchase_price <= 0) {
                $targetProduct->purchase_price = $sourceBatch->unit_price;
            }

            $targetProduct->increment('quantity', $quantity);
            $targetStock->increment('quantity', $quantity);

            $targetStock->refresh();
            $targetProduct->refresh();

            $targetLocationRaw = $data['target_location'] ?? null;
            $targetLocation = $targetLocationRaw
                ? (is_string($targetLocationRaw) ? PurchaseLocation::tryFrom($targetLocationRaw) : $targetLocationRaw)
                : $sourceBatch->location;

            // 3. Create or increment target FIFO batch linked to original purchase
            $targetBatch = null;
            if ($sourceBatch->purchase_id) {
                $targetBatch = PurchaseItem::query()
                    ->where('purchase_id', $sourceBatch->purchase_id)
                    ->where('product_id', $targetProduct->id)
                    ->where('location', $targetLocation?->value ?? 'shop')
                    ->where('batch_number', $sourceBatch->batch_number)
                    ->lockForUpdate()
                    ->first();
            }

            if ($targetBatch) {
                $targetBatch->increment('quantity', $quantity);
                $targetBatch->increment('received_quantity', $quantity);
                $targetBatch->refresh();
            } else {
                $targetBatchNumber = $this->generateUniqueBatchNumber($sourceBatch->batch_number);

                $targetBatch = PurchaseItem::create([
                    'purchase_id' => $sourceBatch->purchase_id,
                    'product_id' => $targetProduct->id,
                    'location' => $targetLocation?->value ?? 'shop',
                    'quantity' => $quantity,
                    'received_quantity' => $quantity,
                    'unit_price' => $sourceBatch->unit_price,
                    'selling_price' => $targetProduct->selling_price ?: $targetProduct->mrp ?: $sourceBatch->selling_price,
                    'batch_number' => $targetBatchNumber,
                    'manufacturing_date' => $sourceBatch->manufacturing_date,
                    'expiry_date' => $sourceBatch->expiry_date,
                    'subtotal' => round($quantity * (float) $sourceBatch->unit_price, 2),
                ]);
            }

            InventoryMovement::create([
                'product_id' => $targetProductId,
                'purchase_id' => $sourceBatch->purchase_id,
                'purchase_item_id' => $targetBatch->id,
                'location' => $targetLocation?->value ?? 'shop',
                'type' => 'reallocation_in',
                'quantity' => $quantity,
                'balance_after' => $targetStock->quantity,
                'reference' => $adjustmentNumber,
                'notes' => "Received {$quantity} unit(s) reallocated from {$sourceProduct->name}. Reason: {$reason}.".($notes ? " Notes: {$notes}" : ''),
            ]);

            // 4. Record InventoryAdjustment log
            return InventoryAdjustment::create([
                'adjustment_number' => $adjustmentNumber,
                'type' => 'reallocation',
                'source_product_id' => $sourceProductId,
                'source_purchase_item_id' => $sourceBatch->id,
                'source_location' => $sourceBatch->location?->value ?? 'shop',
                'target_product_id' => $targetProductId,
                'target_purchase_item_id' => $targetBatch->id,
                'target_location' => $targetLocation?->value ?? 'shop',
                'quantity' => $quantity,
                'unit_cost' => $sourceBatch->unit_price,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Move stock of the same product between locations (Shop <-> Parlour).
     */
    public function transferLocation(array $data, int $userId): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            $productId = (int) ($data['product_id'] ?? 0);
            $sourceBatchId = (int) ($data['source_purchase_item_id'] ?? 0);
            $quantity = (int) ($data['quantity'] ?? 0);
            $targetLocationRaw = $data['target_location'] ?? null;
            $reason = trim($data['reason'] ?? 'location_transfer');
            $notes = trim($data['notes'] ?? '');

            if ($quantity <= 0) {
                throw new InvalidArgumentException('Quantity must be greater than zero.');
            }

            $product = Product::where('id', $productId)->lockForUpdate()->first();
            if (! $product) {
                throw new RuntimeException("Product #{$productId} not found.");
            }

            $stock = InventoryStock::firstOrCreate(
                ['product_id' => $productId],
                ['quantity' => $product->quantity ?? 0]
            );
            $stock = InventoryStock::where('id', $stock->id)->lockForUpdate()->first();

            $sourceBatch = PurchaseItem::where('id', $sourceBatchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $sourceBatch) {
                throw new RuntimeException("Source batch #{$sourceBatchId} not found for product #{$productId}.");
            }

            if ((int) $sourceBatch->received_quantity < $quantity) {
                throw new RuntimeException(
                    "Insufficient batch stock. Available: {$sourceBatch->received_quantity} unit(s), requested: {$quantity} unit(s)."
                );
            }

            $sourceLocation = $sourceBatch->location;
            $targetLocation = $targetLocationRaw
                ? (is_string($targetLocationRaw) ? PurchaseLocation::tryFrom($targetLocationRaw) : $targetLocationRaw)
                : ($sourceLocation === PurchaseLocation::SHOP ? PurchaseLocation::PARLOUR : PurchaseLocation::SHOP);

            if ($targetLocation === $sourceLocation) {
                throw new InvalidArgumentException('Source and target locations must be different.');
            }

            $adjustmentNumber = InventoryAdjustment::generateAdjustmentNumber();

            // 1. Decrement source batch
            $sourceBatch->decrement('received_quantity', $quantity);
            $sourceBatch->refresh();

            InventoryMovement::create([
                'product_id' => $productId,
                'purchase_id' => $sourceBatch->purchase_id,
                'purchase_item_id' => $sourceBatch->id,
                'location' => $sourceLocation?->value ?? 'shop',
                'type' => 'location_transfer_out',
                'quantity' => -$quantity,
                'balance_after' => $stock->quantity,
                'reference' => $adjustmentNumber,
                'notes' => "Moved {$quantity} unit(s) from {$sourceLocation->label()} to {$targetLocation->label()}.".($notes ? " Notes: {$notes}" : ''),
            ]);

            // 2. Find or create target batch with new location
            $targetBatch = null;
            if ($sourceBatch->purchase_id) {
                $targetBatch = PurchaseItem::query()
                    ->where('purchase_id', $sourceBatch->purchase_id)
                    ->where('product_id', $productId)
                    ->where('location', $targetLocation->value)
                    ->where('batch_number', $sourceBatch->batch_number)
                    ->lockForUpdate()
                    ->first();
            }

            if ($targetBatch) {
                $targetBatch->increment('received_quantity', $quantity);
                $targetBatch->refresh();
            } else {
                $targetBatchNumber = $this->generateUniqueBatchNumber($sourceBatch->batch_number);

                $targetBatch = PurchaseItem::create([
                    'purchase_id' => $sourceBatch->purchase_id,
                    'product_id' => $productId,
                    'location' => $targetLocation->value,
                    'quantity' => $quantity,
                    'received_quantity' => $quantity,
                    'unit_price' => $sourceBatch->unit_price,
                    'selling_price' => $sourceBatch->selling_price,
                    'batch_number' => $targetBatchNumber,
                    'manufacturing_date' => $sourceBatch->manufacturing_date,
                    'expiry_date' => $sourceBatch->expiry_date,
                    'subtotal' => round($quantity * (float) $sourceBatch->unit_price, 2),
                ]);
            }

            InventoryMovement::create([
                'product_id' => $productId,
                'purchase_id' => $sourceBatch->purchase_id,
                'purchase_item_id' => $targetBatch->id,
                'location' => $targetLocation->value,
                'type' => 'location_transfer_in',
                'quantity' => $quantity,
                'balance_after' => $stock->quantity,
                'reference' => $adjustmentNumber,
                'notes' => "Received {$quantity} unit(s) moved from {$sourceLocation->label()} into {$targetLocation->label()}.".($notes ? " Notes: {$notes}" : ''),
            ]);

            // Total product quantity is preserved across location transfers.

            return InventoryAdjustment::create([
                'adjustment_number' => $adjustmentNumber,
                'type' => 'location_transfer',
                'source_product_id' => $productId,
                'source_purchase_item_id' => $sourceBatch->id,
                'source_location' => $sourceLocation?->value ?? 'shop',
                'target_product_id' => $productId,
                'target_purchase_item_id' => $targetBatch->id,
                'target_location' => $targetLocation->value,
                'quantity' => $quantity,
                'unit_cost' => $sourceBatch->unit_price,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Quantity adjustment (decrease or increase for damaged, lost, count correction).
     */
    public function adjustQuantity(array $data, int $userId): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            $productId = (int) ($data['product_id'] ?? 0);
            $batchId = ! empty($data['purchase_item_id']) ? (int) $data['purchase_item_id'] : null;
            $type = $data['type'] ?? 'quantity_decrease'; // quantity_decrease or quantity_increase
            $quantity = abs((int) ($data['quantity'] ?? 0));
            $reason = trim($data['reason'] ?? 'count_correction');
            $notes = trim($data['notes'] ?? '');

            if ($quantity <= 0) {
                throw new InvalidArgumentException('Quantity must be greater than zero.');
            }

            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();
            $stock = InventoryStock::firstOrCreate(
                ['product_id' => $productId],
                ['quantity' => $product->quantity ?? 0]
            );
            $stock = InventoryStock::where('id', $stock->id)->lockForUpdate()->first();

            $batch = null;
            if ($batchId) {
                $batch = PurchaseItem::where('id', $batchId)->where('product_id', $productId)->lockForUpdate()->first();
            }

            $adjustmentNumber = InventoryAdjustment::generateAdjustmentNumber();

            if ($type === 'quantity_decrease') {
                if ($batch && (int) $batch->received_quantity < $quantity) {
                    throw new RuntimeException("Insufficient batch stock. Available: {$batch->received_quantity} unit(s).");
                }
                if ((int) $product->quantity < $quantity) {
                    throw new RuntimeException("Insufficient product stock. Available: {$product->quantity} unit(s).");
                }

                $product->decrement('quantity', $quantity);
                $stock->decrement('quantity', $quantity);
                if ($batch) {
                    $batch->decrement('received_quantity', $quantity);
                }

                $stock->refresh();
                $movementType = 'adjustment_decrease';
                $movementQty = -$quantity;
            } else {
                $product->increment('quantity', $quantity);
                $stock->increment('quantity', $quantity);
                if ($batch) {
                    $batch->increment('received_quantity', $quantity);
                }

                $stock->refresh();
                $movementType = 'adjustment_increase';
                $movementQty = $quantity;
            }

            InventoryMovement::create([
                'product_id' => $productId,
                'purchase_id' => $batch?->purchase_id,
                'purchase_item_id' => $batch?->id,
                'type' => $movementType,
                'quantity' => $movementQty,
                'balance_after' => $stock->quantity,
                'reference' => $adjustmentNumber,
                'notes' => "Stock adjustment ({$type}). Reason: {$reason}.".($notes ? " Notes: {$notes}" : ''),
            ]);

            return InventoryAdjustment::create([
                'adjustment_number' => $adjustmentNumber,
                'type' => $type,
                'source_product_id' => $productId,
                'source_purchase_item_id' => $batch?->id,
                'quantity' => $quantity,
                'unit_cost' => $batch?->unit_price ?? $product->purchase_price ?? 0,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Quickly create a new product during reallocation if it doesn't exist yet.
     */
    public function createQuickProduct(array $data, int $userId): Product
    {
        return DB::transaction(function () use ($data, $userId) {
            $name = trim($data['name'] ?? '');
            if (empty($name)) {
                throw new InvalidArgumentException('Product name is required.');
            }

            $sku = ! empty($data['sku']) ? trim($data['sku']) : $this->generateUniqueSku();
            $productCode = ! empty($data['product_code']) ? trim($data['product_code']) : $sku;
            $mrp = (float) ($data['mrp'] ?? 0);
            $sellingPrice = ! empty($data['selling_price']) ? (float) $data['selling_price'] : $mrp;
            $purchasePrice = ! empty($data['purchase_price']) ? (float) $data['purchase_price'] : 0;

            $product = Product::create([
                'name' => $name,
                'sku' => $sku,
                'product_code' => $productCode,
                'company_id' => ! empty($data['company_id']) ? (int) $data['company_id'] : null,
                'category_id' => ! empty($data['category_id']) ? (int) $data['category_id'] : null,
                'unit_id' => ! empty($data['unit_id']) ? (int) $data['unit_id'] : null,
                'mrp' => $mrp,
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'quantity' => 0,
                'min_stock' => (int) ($data['min_stock'] ?? 0),
                'is_active' => true,
                'description' => $data['description'] ?? null,
            ]);

            InventoryStock::create([
                'product_id' => $product->id,
                'quantity' => 0,
            ]);

            if ($mrp > 0) {
                $product->priceHistories()->create([
                    'changed_by' => $userId,
                    'source' => 'product',
                    'reference' => 'Initial product price',
                    'new_mrp' => $mrp,
                    'notes' => 'Product created via quick inventory reallocation.',
                ]);
            }

            return $product;
        });
    }

    public function generateUniqueBatchNumber(?string $baseBatchNumber = null): string
    {
        if ($baseBatchNumber) {
            $candidate = $baseBatchNumber.'-R';
            $counter = 1;
            while (PurchaseItem::where('batch_number', $candidate)->exists()) {
                $candidate = $baseBatchNumber.'-R'.$counter;
                $counter++;
            }

            return $candidate;
        }

        $prefix = 'BN-'.now()->format('Ymd').'-';
        do {
            $candidate = $prefix.strtoupper(Str::random(4));
        } while (PurchaseItem::where('batch_number', $candidate)->exists());

        return $candidate;
    }

    private function generateUniqueSku(): string
    {
        $prefix = 'P.'.date('ymd').'.';

        do {
            $sku = $prefix.strtoupper(Str::random(4));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }
}
