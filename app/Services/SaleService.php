<?php

namespace App\Services;

use App\DTOs\SaleData;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\DiscountCouponException;
use App\Exceptions\SaleException;
use App\Models\DiscountCoupon;
use App\Models\FinanceTransaction;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Exception;
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
                $productIds = collect($data->items)->pluck('product_id')->sort()->values()->all();

                $products = Product::whereIn('id', $productIds)
                    ->lockForUpdate()
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
                    'total_discount' => 0,
                    'total' => 0,
                    'discount_coupon_id' => $appliedCoupon?->id,
                    'coupon_code' => $appliedCoupon?->code ?? $data->coupon_code,
                ]);

                $totalSubtotal = 0;
                $totalDiscount = 0;

                foreach ($data->items as $itemData) {
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

                    $unitPrice = $product->selling_price;
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
                        'cost_price' => $product->purchase_price,
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'final_price' => $finalPrice,
                        'subtotal' => $subtotal,
                    ]);

                    // Update stock and consume oldest received batches first.
                    $product->quantity -= $itemData->quantity;
                    $product->save();
                    $this->inventoryService->adjustForSale($product->id, -$itemData->quantity, 'sale', $sale->invoice_number, 'Sold via sale.', $sale, $saleItem);
                    $this->inventoryService->deductFromBatches($product->id, $itemData->quantity, 'sale', $sale->invoice_number, 'Sold via sale.', $sale, $saleItem);

                    $totalSubtotal = round($totalSubtotal + $subtotal, 2);
                    $totalDiscount = round($totalDiscount + $discount * $quantity, 2);
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

                $rawBillDiscount = abs((float) $data->bill_discount);
                $rawGlobalDiscount = abs((float) $data->global_discount);
                $billDiscount = (float) ($rawBillDiscount > 0 ? $rawBillDiscount : (empty($appliedCoupon) ? $rawGlobalDiscount : 0));
                $globalDiscount = round($couponDiscount + $billDiscount, 2);

                if ($globalDiscount > $totalSubtotal) {
                    throw SaleException::invalidDiscount('Discount ('.format_money($globalDiscount).') cannot exceed subtotal ('.format_money($totalSubtotal).').');
                }

                $total = round($totalSubtotal - $globalDiscount, 2);

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
