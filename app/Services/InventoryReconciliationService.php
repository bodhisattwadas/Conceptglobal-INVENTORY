<?php

namespace App\Services;

use App\Enums\PurchaseLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryReconciliation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryReconciliationService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Link an existing product from master catalog and reconcile.
     */
    public function linkExistingProduct(
        InventoryReconciliation $reconciliation,
        int $productId,
        ?int $purchaseItemId = null,
        bool $deductStock = true,
        ?string $notes = null,
        ?int $userId = null
    ): InventoryReconciliation {
        return DB::transaction(function () use ($reconciliation, $productId, $purchaseItemId, $deductStock, $notes, $userId) {
            $product = Product::findOrFail($productId);
            $saleItem = $reconciliation->saleItem;
            $sale = $reconciliation->sale;

            if ($deductStock && $saleItem) {
                // Deduct from product quantity
                $product->decrement('quantity', $saleItem->quantity);

                // Adjust stock in inventory_stocks
                $this->inventoryService->adjustForSale(
                    $product->id,
                    -$saleItem->quantity,
                    'reconciliation_deduct',
                    $sale->invoice_number,
                    'Deducted via reconciliation for manual sale item: '.$reconciliation->item_name,
                    $sale,
                    $saleItem
                );

                // Deduct from FIFO batches if batch exists
                try {
                    $this->inventoryService->deductFromBatches(
                        $product->id,
                        $saleItem->quantity,
                        'reconciliation_deduct',
                        $sale->invoice_number,
                        'Deducted from FIFO batches for reconciliation.',
                        $sale,
                        $saleItem
                    );
                } catch (\Throwable) {
                    // Batches may not have sufficient stock; general inventory stock already adjusted
                }
            }

            // Update sale item to point to real product
            if ($saleItem) {
                $saleItem->update([
                    'product_id' => $product->id,
                    'cost_price' => $product->purchase_price ?: $saleItem->cost_price,
                ]);
            }

            $currentNotes = $reconciliation->notes ? $reconciliation->notes."\n" : '';
            $resolutionNote = $notes ? "[Reconciled]: {$notes}" : "[Reconciled]: Linked to product '{$product->name}' (SKU: {$product->sku})";

            $reconciliation->update([
                'status' => 'reconciled',
                'reconciled_product_id' => $product->id,
                'reconciled_purchase_item_id' => $purchaseItemId,
                'reconciled_by' => $userId ?? Auth::id(),
                'reconciled_at' => now(),
                'action_taken' => 'linked_existing',
                'cost_price' => $product->purchase_price ?: $reconciliation->cost_price,
                'notes' => trim($currentNotes.$resolutionNote),
            ]);

            return $reconciliation->fresh(['reconciledProduct', 'reconciler']);
        });
    }

    /**
     * Create a brand new product master entry and reconcile.
     */
    public function createNewProductAndReconcile(
        InventoryReconciliation $reconciliation,
        array $productData,
        int $initialStock = 0,
        bool $deductSoldStock = true,
        ?string $notes = null,
        ?int $userId = null
    ): InventoryReconciliation {
        return DB::transaction(function () use ($reconciliation, $productData, $initialStock, $deductSoldStock, $notes, $userId) {
            $sku = ! empty($productData['sku']) ? $productData['sku'] : Product::generateSku();
            $productCode = ! empty($productData['product_code']) ? $productData['product_code'] : ($reconciliation->barcode ?: null);

            $soldQty = $reconciliation->quantity;
            $netStock = $initialStock;
            if ($deductSoldStock && $initialStock >= $soldQty) {
                $netStock = $initialStock - $soldQty;
            } elseif ($deductSoldStock && $initialStock < $soldQty) {
                $netStock = 0;
            }

            $product = Product::create([
                'name' => $productData['name'] ?? $reconciliation->item_name,
                'sku' => $sku,
                'product_code' => $productCode,
                'company_id' => $productData['company_id'] ?? null,
                'category_id' => $productData['category_id'] ?? null,
                'unit_id' => $productData['unit_id'] ?? null,
                'selling_price' => $productData['selling_price'] ?? $reconciliation->unit_price,
                'mrp' => $productData['mrp'] ?? $productData['selling_price'] ?? $reconciliation->unit_price,
                'purchase_price' => $productData['purchase_price'] ?? $reconciliation->cost_price ?? 0,
                'min_stock' => $productData['min_stock'] ?? 0,
                'quantity' => $netStock,
                'location' => PurchaseLocation::SHOP,
                'is_active' => true,
                'description' => $productData['description'] ?? 'Created during POS inventory reconciliation',
            ]);

            // Create inventory stock record
            InventoryStock::create([
                'product_id' => $product->id,
                'location' => PurchaseLocation::SHOP,
                'quantity' => $netStock,
            ]);

            $saleItem = $reconciliation->saleItem;
            $sale = $reconciliation->sale;

            if ($saleItem) {
                $saleItem->update([
                    'product_id' => $product->id,
                    'cost_price' => $product->purchase_price,
                ]);

                if ($sale && $deductSoldStock) {
                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'sale_id' => $sale->id,
                        'sale_item_id' => $saleItem->id,
                        'type' => 'sale',
                        'location' => PurchaseLocation::SHOP,
                        'quantity' => -$soldQty,
                        'balance_after' => $netStock,
                        'reference' => $sale->invoice_number,
                        'notes' => 'Sold at POS via manual entry, reconciled with newly created product.',
                    ]);
                }
            }

            $currentNotes = $reconciliation->notes ? $reconciliation->notes."\n" : '';
            $resolutionNote = $notes ? "[Reconciled]: {$notes}" : "[Reconciled]: Created new product '{$product->name}' (SKU: {$product->sku})";

            $reconciliation->update([
                'status' => 'reconciled',
                'reconciled_product_id' => $product->id,
                'reconciled_by' => $userId ?? Auth::id(),
                'reconciled_at' => now(),
                'action_taken' => 'created_new_product',
                'cost_price' => $product->purchase_price,
                'notes' => trim($currentNotes.$resolutionNote),
            ]);

            return $reconciliation->fresh(['reconciledProduct', 'reconciler']);
        });
    }

    /**
     * Direct mark as reconciled with administrative notes.
     */
    public function markAsReconciledDirectly(
        InventoryReconciliation $reconciliation,
        ?string $notes = null,
        ?int $userId = null
    ): InventoryReconciliation {
        $currentNotes = $reconciliation->notes ? $reconciliation->notes."\n" : '';
        $resolutionNote = $notes ? "[Reconciled]: {$notes}" : '[Reconciled]: Marked as reconciled by administrator.';

        $reconciliation->update([
            'status' => 'reconciled',
            'reconciled_by' => $userId ?? Auth::id(),
            'reconciled_at' => now(),
            'action_taken' => 'manual_mark',
            'notes' => trim($currentNotes.$resolutionNote),
        ]);

        return $reconciliation->fresh(['reconciler']);
    }

    /**
     * Auto-reconcile against an existing product if matching barcode or matched_product_id exists.
     */
    public function autoReconcile(InventoryReconciliation $reconciliation, ?int $userId = null): bool
    {
        $productId = $reconciliation->matched_product_id;

        if (! $productId && ! empty($reconciliation->barcode)) {
            $matched = Product::where('product_code', $reconciliation->barcode)
                ->orWhere('sku', $reconciliation->barcode)
                ->first();
            $productId = $matched?->id;
        }

        if (! $productId && ! empty($reconciliation->item_name)) {
            $matched = Product::where('name', $reconciliation->item_name)->first();
            $productId = $matched?->id;
        }

        if (! $productId) {
            return false;
        }

        $this->linkExistingProduct(
            reconciliation: $reconciliation,
            productId: $productId,
            purchaseItemId: null,
            deductStock: true,
            notes: 'Auto-reconciled based on matching barcode/name.',
            userId: $userId
        );

        return true;
    }

    /**
     * Update all details of an inventory reconciliation record and optionally sync with sale.
     */
    public function updateReconciliationDetails(
        InventoryReconciliation $reconciliation,
        array $data,
        bool $syncWithSaleItem = true
    ): InventoryReconciliation {
        return DB::transaction(function () use ($reconciliation, $data, $syncWithSaleItem) {
            $unitPrice = isset($data['unit_price']) ? (float) $data['unit_price'] : (float) $reconciliation->unit_price;
            $quantity = isset($data['quantity']) ? (int) $data['quantity'] : (int) $reconciliation->quantity;
            $discount = isset($data['discount']) ? (float) $data['discount'] : (float) $reconciliation->discount;
            $costPrice = isset($data['cost_price']) ? (float) $data['cost_price'] : (float) $reconciliation->cost_price;
            $finalPrice = round(max(0, $unitPrice - $discount), 2);
            $subtotal = round($finalPrice * $quantity, 2);

            $itemName = isset($data['item_name']) ? trim($data['item_name']) : $reconciliation->item_name;
            $barcode = array_key_exists('barcode', $data) ? trim((string) $data['barcode']) : $reconciliation->barcode;
            $notes = array_key_exists('notes', $data) ? $data['notes'] : $reconciliation->notes;
            $status = $data['status'] ?? $reconciliation->status;

            $reconciliation->update([
                'item_name' => $itemName,
                'barcode' => $barcode ?: null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice,
                'discount' => $discount,
                'subtotal' => $subtotal,
                'notes' => $notes,
                'status' => $status,
                'matched_product_id' => $data['matched_product_id'] ?? $reconciliation->matched_product_id,
            ]);

            // Sync with SaleItem and Sale if requested
            if ($syncWithSaleItem && $reconciliation->saleItem) {
                $saleItem = $reconciliation->saleItem;
                $saleItem->update([
                    'custom_name' => $itemName,
                    'barcode' => $barcode ?: null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'final_price' => $finalPrice,
                    'subtotal' => $subtotal,
                ]);

                // Recalculate parent sale totals
                $sale = $reconciliation->sale;
                if ($sale) {
                    $newSubtotal = $sale->items()->sum('subtotal');
                    $totalItemDiscounts = $sale->items()->sum(DB::raw('discount * quantity'));
                    $globalDiscount = (float) $sale->global_discount;
                    $returnDiscount = (float) $sale->return_discount;
                    $newTotal = max(0, round($newSubtotal - $globalDiscount - $returnDiscount, 2));

                    $sale->update([
                        'subtotal' => $newSubtotal,
                        'total_discount' => round($totalItemDiscounts + $globalDiscount + $returnDiscount, 2),
                        'total' => $newTotal,
                    ]);
                }
            }

            return $reconciliation->fresh();
        });
    }
}
