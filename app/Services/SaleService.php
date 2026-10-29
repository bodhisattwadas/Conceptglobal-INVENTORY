<?php

namespace App\Services;

use App\DTOs\SaleData;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\DiscountCouponException;
use App\Exceptions\SaleException;
use App\Models\AuditLog;
use App\Models\DiscountCoupon;
use App\Models\FinanceTransaction;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        protected FinanceTransactionService $financeService,
        protected InventoryService $inventoryService,
        protected DiscountCouponService $couponService
    ) {}

    /**
     * Create a new sale with items and deduction of stock.
     */
    public function createSale(SaleData $data): Sale
    {
        return DB::transaction(function () use ($data) {
            try {
                // Lock products for update
                $productIds = collect($data->items)->pluck('product_id')->filter()->sort()->values()->all();

                $products = Product::whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $serviceIds = collect($data->items)->pluck('service_id')->filter()->sort()->values()->all();
                $services = Service::whereIn('id', $serviceIds)
                    ->get()
                    ->keyBy('id');

                $appliedCoupon = null;
                if ($data->coupon_id || $data->coupon_code) {
                    $couponQuery = DiscountCoupon::query();
                    if ($data->coupon_id) {
                        $couponQuery->where('id', $data->coupon_id);
                    } else {
                        $couponQuery->where('code', $data->coupon_code);
                    }
                    $appliedCoupon = $couponQuery->lockForUpdate()->first();
                }

                $sale = Sale::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'customer_id' => $data->customer_id,
                    'created_by' => $data->created_by,
                    'sale_date' => $data->sale_date,
                    'status' => $data->status,
                    'payment_method' => $data->payment_method,
                    'notes' => $data->notes,
                    'cash_received' => $data->payment_method === PaymentMethod::CASH ? $data->cash_received : 0,
                    'change' => 0,
                    'subtotal' => 0,
                    'global_discount' => $data->global_discount,
                    'coupon_discount' => 0,
                    'round_off' => 0,
                    'total_discount' => 0,
                    'total' => 0,
                    'discount_coupon_id' => $appliedCoupon?->id,
                    'coupon_code' => $appliedCoupon?->code ?? $data->coupon_code,
                ]);

                $totalSubtotal = 0;
                $totalDiscount = 0;

                // Ensure products are created at top, then services
                $sortedItems = $data->items;
                usort($sortedItems, function ($a, $b) {
                    $aRank = $a->service_id ? 1 : 0;
                    $bRank = $b->service_id ? 1 : 0;

                    return $aRank <=> $bRank;
                });

                foreach ($sortedItems as $itemData) {
                    if ($itemData->service_id) {
                        $service = $services->get($itemData->service_id);

                        if (! $service) {
                            throw SaleException::serviceNotFound($itemData->service_id);
                        }

                        $unitPrice = $itemData->unit_price;
                        $quantity = $itemData->quantity;
                        $discount = $itemData->discount;

                        if ($discount > $unitPrice) {
                            throw SaleException::invalidDiscount('Item discount ('.format_money($discount).') cannot exceed unit price ('.format_money($unitPrice).") for service '{$service->name}'.");
                        }

                        $finalPrice = round($unitPrice - $discount, 2);
                        $subtotal = round($finalPrice * $quantity, 2);

                        SaleItem::create([
                            'sale_id' => $sale->id,
                            'product_id' => null,
                            'service_id' => $service->id,
                            'quantity' => $quantity,
                            'cost_price' => $service->cost,
                            'unit_price' => $unitPrice,
                            'discount' => $discount,
                            'final_price' => $finalPrice,
                            'subtotal' => $subtotal,
                        ]);

                        $totalSubtotal = round($totalSubtotal + $subtotal, 2);
                        $totalDiscount = round($totalDiscount + $discount * $quantity, 2);

                        continue;
                    }

                    $product = $products->get($itemData->product_id);

                    if (! $product) {
                        throw SaleException::productNotFound($itemData->product_id);
                    }

                    if ($product->quantity < $itemData->quantity) {
                        throw SaleException::insufficientStock(
                            $product->name,
                            $itemData->quantity,
                            $product->quantity
                        );
                    }

                    $unitPrice = $itemData->unit_price > 0 ? $itemData->unit_price : (float) $product->selling_price;
                    $quantity = $itemData->quantity;
                    $discount = $itemData->discount;

                    if ($discount > $unitPrice) {
                        throw SaleException::invalidDiscount('Item discount ('.format_money($discount).') cannot exceed unit price ('.format_money($unitPrice).") for product '{$product->name}'.");
                    }

                    $finalPrice = round($unitPrice - $discount, 2);
                    $subtotal = round($finalPrice * $quantity, 2);

                    $saleItem = SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'cost_price' => $product->purchase_price ?: 0,
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'final_price' => $finalPrice,
                        'subtotal' => $subtotal,
                    ]);

                    $totalSubtotal = round($totalSubtotal + $subtotal, 2);
                    $totalDiscount = round($totalDiscount + $discount * $quantity, 2);

                    // Update stock: decrement product and inventory stock by sold quantity
                    $product->decrement('quantity', $quantity);
                    $this->inventoryService->adjustForSale($product->id, -$quantity, 'sale', $sale->invoice_number, 'Sold via sale.', $sale, $saleItem);
                    $this->inventoryService->deductFromBatches($product->id, $quantity, 'sale', $sale->invoice_number, 'Deducted from FIFO batches for sale.', $sale, $saleItem);
                }

                $couponDiscount = 0.0;
                if ($appliedCoupon) {
                    try {
                        $couponResult = $this->couponService->validateAndCalculate($appliedCoupon, $totalSubtotal);
                        $couponDiscount = (float) $couponResult['discount_amount'];
                        $this->couponService->incrementUsage($appliedCoupon);
                    } catch (DiscountCouponException $e) {
                        throw SaleException::invalidDiscount($e->getMessage());
                    }
                }

                if ($data->round_off !== null) {
                    $roundOff = round($data->round_off, 2);
                    $globalDiscount = round($couponDiscount - $roundOff, 2);

                    if ($globalDiscount > $totalSubtotal) {
                        throw SaleException::invalidDiscount('Discount ('.format_money($globalDiscount).') cannot exceed subtotal ('.format_money($totalSubtotal).').');
                    }

                    $total = max(0, round($totalSubtotal - $globalDiscount, 2));
                } else {
                    $rawBillDiscount = abs((float) $data->bill_discount);
                    $rawGlobalDiscount = abs((float) $data->global_discount);
                    $billDiscount = (float) ($rawBillDiscount > 0 ? $rawBillDiscount : (empty($appliedCoupon) ? $rawGlobalDiscount : 0));
                    $globalDiscount = round($couponDiscount + $billDiscount, 2);
                    $roundOff = round(-$billDiscount, 2);

                    if ($globalDiscount > $totalSubtotal) {
                        throw SaleException::invalidDiscount('Discount ('.format_money($globalDiscount).') cannot exceed subtotal ('.format_money($totalSubtotal).').');
                    }

                    $total = round($totalSubtotal - $globalDiscount, 2);
                }

                if ($data->status === SaleStatus::COMPLETED) {
                    if ($data->payment_method === PaymentMethod::CASH && $data->cash_received < $total) {
                        throw SaleException::insufficientPayment($total, $data->cash_received);
                    }
                }
                $change = 0;

                // Calculate change if payment method is cash
                if ($data->payment_method === PaymentMethod::CASH && $sale->cash_received >= $total) {
                    $change = round($sale->cash_received - $total, 2);
                }

                $sale->update([
                    'subtotal' => round($totalSubtotal + $totalDiscount, 2),
                    'total_discount' => round($totalDiscount + $globalDiscount, 2),
                    'global_discount' => $globalDiscount,
                    'coupon_discount' => $couponDiscount,
                    'round_off' => $roundOff,
                    'total' => $total,
                    'cash_received' => $data->payment_method === PaymentMethod::CASH ? $data->cash_received : $total,
                    'change' => $change,
                    'discount_coupon_id' => $appliedCoupon?->id,
                    'coupon_code' => $appliedCoupon?->code ?? $data->coupon_code,
                ]);

                if ($sale->status === SaleStatus::COMPLETED) {
                    $this->financeService->recordIncomeFromSale($sale);
                }

                return $sale;

            } catch (Exception $e) {
                if ($e instanceof SaleException) {
                    throw $e;
                }
                throw SaleException::creationFailed($e->getMessage(), ['data' => $data]);
            }
        });
    }

    /**
     * Safely update numbers, prices, payment method, and metadata on a historical sale,
     * synchronizing inventory stock, batch remaining quantities, movements, finance records, and audit logs.
     */
    public function updateHistoricalSale(Sale $sale, SaleData $data, ?string $reason = null, ?string $paymentRef = null): Sale
    {
        return DB::transaction(function () use ($sale, $data, $reason, $paymentRef) {
            try {
                if ($sale->status === SaleStatus::CANCELLED) {
                    throw SaleException::invalidStatus('update', $sale->status->label(), ['id' => $sale->id]);
                }

                $sale->loadMissing(['items.product', 'items.service', 'customer']);

                // Snapshot old state for audit logging
                $oldState = [
                    'customer_id' => $sale->customer_id,
                    'customer_name' => $sale->customer?->name,
                    'sale_date' => $sale->sale_date?->toDateTimeString(),
                    'payment_method' => $sale->payment_method?->value,
                    'subtotal' => (float) $sale->subtotal,
                    'global_discount' => (float) $sale->global_discount,
                    'coupon_discount' => (float) $sale->coupon_discount,
                    'round_off' => (float) $sale->round_off,
                    'total_discount' => (float) $sale->total_discount,
                    'total' => (float) $sale->total,
                    'cash_received' => (float) $sale->cash_received,
                    'change' => (float) $sale->change,
                    'notes' => $sale->notes,
                    'items' => $sale->items->map(fn ($item) => [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'service_id' => $item->service_id,
                        'name' => $item->item_name,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'discount' => (float) $item->discount,
                        'subtotal' => (float) $item->subtotal,
                    ])->all(),
                ];

                // 1. Revert previous inventory deductions for existing items
                foreach ($sale->items as $item) {
                    if ($item->product_id && $item->product) {
                        $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                        if ($product) {
                            $product->increment('quantity', $item->quantity);
                        }
                        $this->inventoryService->adjustForSale(
                            $item->product_id,
                            $item->quantity,
                            'sale_edit',
                            $sale->invoice_number,
                            'Reversed prior to historical sale edit.',
                            $sale,
                            $item
                        );

                        $originalType = InventoryMovement::query()
                            ->where('sale_item_id', $item->id)
                            ->whereIn('type', ['sale', 'sale_restore', 'sale_edit_deduct'])
                            ->pluck('type')
                            ->unique()
                            ->all();
                        if (empty($originalType)) {
                            $originalType = ['sale', 'sale_restore'];
                        }

                        $this->inventoryService->restoreBatchesForReference(
                            $item->product_id,
                            $sale->invoice_number,
                            $originalType,
                            'sale_edit_restore',
                            'Reversed batches for historical sale edit.',
                            $sale,
                            $item
                        );
                    }
                }

                // Delete existing items to be recreated fresh
                $sale->items()->delete();

                // 2. Lock products and services for new items
                $productIds = collect($data->items)->pluck('product_id')->filter()->sort()->values()->all();
                $products = Product::whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $serviceIds = collect($data->items)->pluck('service_id')->filter()->sort()->values()->all();
                $services = Service::whereIn('id', $serviceIds)
                    ->get()
                    ->keyBy('id');

                // 3. Coupon Handling
                $oldCouponId = $sale->discount_coupon_id;
                $newCouponId = $data->coupon_id;
                $appliedCoupon = null;

                if ($data->coupon_id || $data->coupon_code) {
                    $couponQuery = DiscountCoupon::query();
                    if ($data->coupon_id) {
                        $couponQuery->where('id', $data->coupon_id);
                    } else {
                        $couponQuery->where('code', $data->coupon_code);
                    }
                    $appliedCoupon = $couponQuery->lockForUpdate()->first();
                }

                if ($oldCouponId && (! $appliedCoupon || $appliedCoupon->id !== $oldCouponId)) {
                    $oldCoupon = DiscountCoupon::find($oldCouponId);
                    if ($oldCoupon) {
                        $this->couponService->decrementUsage($oldCoupon);
                    }
                }

                $totalSubtotal = 0;
                $totalDiscount = 0;

                // Ensure products are created at top, then services
                $sortedItems = $data->items;
                usort($sortedItems, function ($a, $b) {
                    $aRank = $a->service_id ? 1 : 0;
                    $bRank = $b->service_id ? 1 : 0;

                    return $aRank <=> $bRank;
                });

                // 4. Create new items and apply inventory deductions
                foreach ($sortedItems as $itemData) {
                    if ($itemData->service_id) {
                        $service = $services->get($itemData->service_id);
                        if (! $service) {
                            throw SaleException::serviceNotFound($itemData->service_id);
                        }

                        $unitPrice = $itemData->unit_price;
                        $quantity = $itemData->quantity;
                        $discount = $itemData->discount;

                        if ($discount > $unitPrice) {
                            throw SaleException::invalidDiscount('Item discount ('.format_money($discount).') cannot exceed unit price ('.format_money($unitPrice).") for service '{$service->name}'.");
                        }

                        $finalPrice = round($unitPrice - $discount, 2);
                        $subtotal = round($finalPrice * $quantity, 2);

                        SaleItem::create([
                            'sale_id' => $sale->id,
                            'product_id' => null,
                            'service_id' => $service->id,
                            'quantity' => $quantity,
                            'cost_price' => $service->cost,
                            'unit_price' => $unitPrice,
                            'discount' => $discount,
                            'final_price' => $finalPrice,
                            'subtotal' => $subtotal,
                        ]);

                        $totalSubtotal = round($totalSubtotal + $subtotal, 2);
                        $totalDiscount = round($totalDiscount + $discount * $quantity, 2);

                        continue;
                    }

                    $product = $products->get($itemData->product_id);
                    if (! $product) {
                        throw SaleException::productNotFound($itemData->product_id);
                    }

                    $unitPrice = $itemData->unit_price > 0 ? $itemData->unit_price : $product->selling_price;
                    $quantity = $itemData->quantity;
                    $discount = $itemData->discount;

                    if ($discount > $unitPrice) {
                        throw SaleException::invalidDiscount('Item discount ('.format_money($discount).') cannot exceed unit price ('.format_money($unitPrice).") for product '{$product->name}'.");
                    }

                    $finalPrice = round($unitPrice - $discount, 2);
                    $subtotal = round($finalPrice * $quantity, 2);

                    $saleItem = SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'cost_price' => $product->purchase_price ?: 0,
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'final_price' => $finalPrice,
                        'subtotal' => $subtotal,
                    ]);

                    // Deduct from available FIFO batches
                    $this->inventoryService->deductFromBatches($product->id, $quantity, 'sale_edit_deduct', $sale->invoice_number, 'Deducted for historical sale edit.', $sale, $saleItem);

                    $product->quantity -= $quantity;
                    $product->save();

                    $this->inventoryService->adjustForSale($product->id, -$quantity, 'sale_edit_deduct', $sale->invoice_number, 'Deducted for historical sale edit.', $sale, $saleItem);

                    $totalSubtotal = round($totalSubtotal + $subtotal, 2);
                    $totalDiscount = round($totalDiscount + $discount * $quantity, 2);
                }

                // 5. Coupon calculation
                $couponDiscount = 0.0;
                if ($appliedCoupon) {
                    try {
                        $couponResult = $this->couponService->validateAndCalculate($appliedCoupon, $totalSubtotal);
                        $couponDiscount = (float) $couponResult['discount_amount'];
                        if (! $oldCouponId || $oldCouponId !== $appliedCoupon->id) {
                            $this->couponService->incrementUsage($appliedCoupon);
                        }
                    } catch (DiscountCouponException $e) {
                        throw SaleException::invalidDiscount($e->getMessage());
                    }
                }

                // 6. Round off & totals
                if ($data->round_off !== null) {
                    $roundOff = round($data->round_off, 2);
                    $globalDiscount = round($couponDiscount - $roundOff, 2);

                    if ($globalDiscount > $totalSubtotal) {
                        throw SaleException::invalidDiscount('Discount ('.format_money($globalDiscount).') cannot exceed subtotal ('.format_money($totalSubtotal).').');
                    }

                    $total = max(0, round($totalSubtotal - $globalDiscount, 2));
                } else {
                    $rawBillDiscount = abs((float) $data->bill_discount);
                    $rawGlobalDiscount = abs((float) $data->global_discount);
                    $billDiscount = (float) ($rawBillDiscount > 0 ? $rawBillDiscount : (empty($appliedCoupon) ? $rawGlobalDiscount : 0));
                    $globalDiscount = round($couponDiscount + $billDiscount, 2);
                    $roundOff = round(-$billDiscount, 2);

                    if ($globalDiscount > $totalSubtotal) {
                        throw SaleException::invalidDiscount('Discount ('.format_money($globalDiscount).') cannot exceed subtotal ('.format_money($totalSubtotal).').');
                    }

                    $total = round($totalSubtotal - $globalDiscount, 2);
                }

                // 7. Payment method & cash tender
                $paymentMethod = $data->payment_method;
                $cashReceived = $paymentMethod === PaymentMethod::CASH ? $data->cash_received : $total;
                $change = 0;

                if ($paymentMethod === PaymentMethod::CASH) {
                    if ($sale->status === SaleStatus::COMPLETED && $cashReceived < $total) {
                        throw SaleException::insufficientPayment($total, $cashReceived);
                    }
                    if ($cashReceived >= $total) {
                        $change = round($cashReceived - $total, 2);
                    }
                }

                // 8. Update Sale record
                $notes = $data->notes;
                if ($paymentRef && trim($paymentRef) !== '') {
                    $cleanRef = trim($paymentRef);
                    if (preg_match('/Ref:\s*[^\|\n\r]+/i', $notes ?? '')) {
                        $notes = preg_replace('/Ref:\s*[^\|\n\r]+/i', 'Ref: '.$cleanRef, $notes);
                    } else {
                        $notes = ($notes ? $notes.' | ' : '').'Ref: '.$cleanRef;
                    }
                }
                if ($reason) {
                    $notes = ($notes ? $notes."\n" : '').'[Edited]: '.$reason;
                }

                $sale->update([
                    'customer_id' => $data->customer_id,
                    'sale_date' => $data->sale_date,
                    'payment_method' => $paymentMethod,
                    'notes' => $notes,
                    'subtotal' => round($totalSubtotal + $totalDiscount, 2),
                    'total_discount' => round($totalDiscount + $globalDiscount, 2),
                    'global_discount' => $globalDiscount,
                    'coupon_discount' => $couponDiscount,
                    'round_off' => $roundOff,
                    'total' => $total,
                    'cash_received' => $cashReceived,
                    'change' => $change,
                    'discount_coupon_id' => $appliedCoupon?->id,
                    'coupon_code' => $appliedCoupon?->code ?? $data->coupon_code,
                ]);

                // 9. Finance Synchronization
                if ($sale->status === SaleStatus::COMPLETED) {
                    $this->financeService->recordIncomeFromSale($sale);
                }

                // 10. Audit Log
                $sale->load(['items.product', 'items.service', 'customer']);
                $newState = [
                    'customer_id' => $sale->customer_id,
                    'customer_name' => $sale->customer?->name,
                    'sale_date' => $sale->sale_date?->toDateTimeString(),
                    'payment_method' => $sale->payment_method?->value,
                    'subtotal' => (float) $sale->subtotal,
                    'global_discount' => (float) $sale->global_discount,
                    'coupon_discount' => (float) $sale->coupon_discount,
                    'round_off' => (float) $sale->round_off,
                    'total_discount' => (float) $sale->total_discount,
                    'total' => (float) $sale->total,
                    'cash_received' => (float) $sale->cash_received,
                    'change' => (float) $sale->change,
                    'notes' => $sale->notes,
                    'items' => $sale->items->map(fn ($item) => [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'service_id' => $item->service_id,
                        'name' => $item->item_name,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'discount' => (float) $item->discount,
                        'subtotal' => (float) $item->subtotal,
                    ])->all(),
                    'payment_ref' => $paymentRef,
                    'reason' => $reason,
                ];

                AuditLog::create([
                    'entity_type' => Sale::class,
                    'entity_id' => $sale->id,
                    'action' => 'historical_edit',
                    'old_values' => $oldState,
                    'new_values' => $newState,
                    'changed_by' => Auth::id() ?? $data->created_by,
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'changed_at' => now(),
                ]);

                return $sale->refresh();

            } catch (Exception $e) {
                if ($e instanceof SaleException) {
                    throw $e;
                }
                throw SaleException::updateFailed($e->getMessage(), ['id' => $sale->id]);
            }
        });
    }

    /**
     * Cancel a sale and restore stock.
     */
    public function cancelSale(Sale $sale, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($sale, $reason) {
            try {
                if ($sale->status === SaleStatus::CANCELLED) {
                    throw SaleException::invalidStatus('cancel', $sale->status->label(), ['id' => $sale->id]);
                }

                // Restore stock for completed or pending sales
                if (in_array($sale->status, [SaleStatus::COMPLETED, SaleStatus::PENDING])) {
                    $sale->loadMissing('items.product');

                    foreach ($sale->items as $item) {
                        if ($item->product) {
                            $item->product->increment('quantity', $item->quantity);
                            $this->inventoryService->adjustForSale($item->product_id, $item->quantity, 'sale_cancel', $sale->invoice_number, 'Restored due to sale cancellation.', $sale, $item);
                            $originalType = InventoryMovement::query()
                                ->where('sale_item_id', $item->id)
                                ->where('type', 'sale_restore')
                                ->exists() ? 'sale_restore' : 'sale';
                            $lastCancellationAt = InventoryMovement::query()
                                ->where('sale_item_id', $item->id)
                                ->where('type', 'sale_cancel')
                                ->max('created_at');

                            $this->inventoryService->restoreBatchesForReference($item->product_id, $sale->invoice_number, $originalType, 'sale_cancel', 'Restored due to sale cancellation.', $sale, $item, $lastCancellationAt);
                        }
                    }
                }

                $updateData = ['status' => SaleStatus::CANCELLED];

                if ($reason) {
                    $updateData['notes'] = ($sale->notes ? $sale->notes."\n" : '').'[Cancelled]: '.$reason;
                }

                $sale->update($updateData);

                if ($sale->discount_coupon_id) {
                    $coupon = DiscountCoupon::find($sale->discount_coupon_id);
                    if ($coupon) {
                        $this->couponService->decrementUsage($coupon);
                    }
                }

                // Void Finance
                $this->financeService->voidTransaction($sale);

                return $sale;

            } catch (Exception $e) {
                if ($e instanceof SaleException) {
                    throw $e;
                }
                throw SaleException::cancellationFailed($e->getMessage(), ['id' => $sale->id]);
            }
        });
    }

    /**
     * Mark a pending sale as completed.
     */
    public function completeSale(Sale $sale, array $paymentData = []): Sale
    {
        return DB::transaction(function () use ($sale, $paymentData) {
            if ($sale->status !== SaleStatus::PENDING) {
                throw SaleException::invalidStatus('complete', $sale->status->label(), ['id' => $sale->id]);
            }

            $cashReceived = round((float) ($paymentData['cash_received'] ?? $sale->cash_received), 2);

            if ($sale->payment_method === PaymentMethod::CASH && $cashReceived < $sale->total) {
                throw SaleException::insufficientPayment($sale->total, $cashReceived);
            }

            $updateData = [
                'status' => SaleStatus::COMPLETED,
                'cash_received' => $sale->payment_method === PaymentMethod::CASH ? $cashReceived : $sale->total,
                'change' => $sale->payment_method === PaymentMethod::CASH ? round($cashReceived - $sale->total, 2) : 0,
            ];

            $sale->update($updateData);

            // Sync Finance
            $this->financeService->recordIncomeFromSale($sale);

            return $sale;
        });
    }

    /**
     * Restore a cancelled sale to pending (must reserve stock again).
     */
    public function restoreSale(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            if ($sale->status !== SaleStatus::CANCELLED) {
                throw SaleException::invalidStatus('restore', $sale->status->label(), ['id' => $sale->id]);
            }

            // Must re-deduct stock
            $sale->loadMissing('items.product');

            foreach ($sale->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = $item->product()->lockForUpdate()->find($item->product_id);

                if (! $product) {
                    throw SaleException::productNotFound($item->product_id);
                }

                if ($product->quantity < $item->quantity) {
                    throw SaleException::insufficientStock(
                        $product->name,
                        $item->quantity,
                        $product->quantity
                    );
                }

                $product->decrement('quantity', $item->quantity);
                $this->inventoryService->adjustForSale($item->product_id, -$item->quantity, 'sale_restore', $sale->invoice_number, 'Deducted due to sale restore from cancelled.', $sale, $item);
                $this->inventoryService->deductFromBatches($item->product_id, $item->quantity, 'sale_restore', $sale->invoice_number, 'Deducted due to sale restore from cancelled.', $sale, $item);
            }

            // Restore to PENDING
            $sale->update(['status' => SaleStatus::PENDING]);

            if ($sale->discount_coupon_id) {
                $coupon = DiscountCoupon::find($sale->discount_coupon_id);
                if ($coupon) {
                    $this->couponService->incrementUsage($coupon);
                }
            }

            // No Finance Sync needed as it goes to PENDING

            return $sale;
        });
    }

    /**
     * Permanently delete a cancelled sale.
     *
     * @throws Exception
     */
    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            if ($sale->status !== SaleStatus::CANCELLED) {
                throw SaleException::invalidStatus('delete', $sale->status->label(), ['id' => $sale->id]);
            }

            // Void Finance (Just in case)
            $this->financeService->voidTransaction($sale);

            // Manually delete items first due to restrictOnDelete constraint
            $sale->items()->delete();
            $sale->delete();
        });
    }

    /**
     * Update the sale date and synchronize associated finance transaction.
     */
    public function updateSaleDate(Sale $sale, Carbon|string $saleDate): Sale
    {
        return DB::transaction(function () use ($sale, $saleDate) {
            $parsedDate = $saleDate instanceof Carbon ? $saleDate : Carbon::parse($saleDate);

            $sale->update([
                'sale_date' => $parsedDate,
            ]);

            FinanceTransaction::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->update([
                    'transaction_date' => $parsedDate,
                ]);

            return $sale;
        });
    }

    /**
     * Update the sale payment method, adjust tender/change, and synchronize finance and audit records.
     */
    public function updatePaymentMethod(Sale $sale, PaymentMethod|string $paymentMethod, ?float $cashReceived = null, ?string $reason = null, ?string $paymentRef = null): Sale
    {
        return DB::transaction(function () use ($sale, $paymentMethod, $cashReceived, $reason, $paymentRef) {
            $oldMethod = $sale->payment_method;
            $newMethod = $paymentMethod instanceof PaymentMethod ? $paymentMethod : PaymentMethod::from($paymentMethod);

            $cash = $newMethod === PaymentMethod::CASH ? round((float) ($cashReceived ?? $sale->total), 2) : $sale->total;
            if ($newMethod === PaymentMethod::CASH && $sale->status === SaleStatus::COMPLETED && $cash < $sale->total) {
                throw SaleException::insufficientPayment($sale->total, $cash);
            }

            $change = ($newMethod === PaymentMethod::CASH && $cash >= $sale->total) ? round($cash - $sale->total, 2) : 0;

            $notes = $sale->notes;
            if ($paymentRef && trim($paymentRef) !== '') {
                $cleanRef = trim($paymentRef);
                if (preg_match('/Ref:\s*[^\|\n\r]+/i', $notes ?? '')) {
                    $notes = preg_replace('/Ref:\s*[^\|\n\r]+/i', 'Ref: '.$cleanRef, $notes);
                } else {
                    $notes = ($notes ? $notes.' | ' : '').'Ref: '.$cleanRef;
                }
            }
            if ($reason) {
                $notes = ($notes ? $notes."\n" : '').'[Payment Method Updated]: '.$reason;
            }

            $oldState = [
                'payment_method' => $oldMethod->value,
                'cash_received' => (float) $sale->cash_received,
                'change' => (float) $sale->change,
            ];

            $sale->update([
                'payment_method' => $newMethod,
                'cash_received' => $cash,
                'change' => $change,
                'notes' => $notes,
            ]);

            if ($sale->status === SaleStatus::COMPLETED) {
                $this->financeService->recordIncomeFromSale($sale);
            }

            AuditLog::create([
                'entity_type' => Sale::class,
                'entity_id' => $sale->id,
                'action' => 'payment_method_edit',
                'old_values' => $oldState,
                'new_values' => [
                    'payment_method' => $newMethod->value,
                    'cash_received' => $cash,
                    'change' => $change,
                    'payment_ref' => $paymentRef,
                    'reason' => $reason,
                ],
                'changed_by' => Auth::id() ?? $sale->created_by,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'changed_at' => now(),
            ]);

            return $sale->refresh();
        });
    }

    /**
     * Generate unique invoice number.
     * Format: INV.YYMMDD.0001
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV.'.date('ymd').'.';

        $latest = Sale::where('invoice_number', 'like', $prefix.'%')
            ->orderBy('id', 'desc')
            ->first();

        if (! $latest) {
            return $prefix.'0001';
        }

        $lastNumber = (int) substr($latest->invoice_number, -4);

        return $prefix.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }
}
