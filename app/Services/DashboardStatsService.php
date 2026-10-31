<?php

namespace App\Services;

use App\Models\FinanceTransaction;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardStatsService
{
    /**
     * Audit and log detailed individual parameters used to calculate KPIs for the given period.
     * Logs every product, invoice, unit price, discounts, net price, COGS, gross profit, and finance transactions.
     */
    public function logKpiBreakdown(Carbon $startDate, Carbon $endDate, string $periodKey): string
    {
        try {
            $startStr = $startDate->copy()->startOfDay()->toDateTimeString();
            $endStr = $endDate->copy()->endOfDay()->toDateTimeString();
            $appTimezone = config('app.timezone', 'UTC');

            $lines = [];
            $lines[] = '================================================================================';
            $lines[] = '[DASHBOARD KPI AUDIT] Period: '.strtoupper($periodKey);
            $lines[] = "Range: {$startStr} to {$endStr} ({$appTimezone})";
            $lines[] = 'Timestamp: '.now()->toDateTimeString()." ({$appTimezone})";
            $lines[] = '--------------------------------------------------------------------------------';

            // Query Completed Sales in Range
            $completedSales = Sale::with(['items.product', 'items.service', 'customer'])
                ->whereBetween('sale_date', [$startStr, $endStr])
                ->where('status', 'completed')
                ->orderBy('sale_date')
                ->get();

            // Query Non-Completed Sales in Range (for audit visibility)
            $nonCompletedSales = Sale::whereBetween('sale_date', [$startStr, $endStr])
                ->where('status', '!=', 'completed')
                ->orderBy('sale_date')
                ->get();

            $totalCalculatedRevenue = 0.0;
            $totalCalculatedSubtotal = 0.0;
            $totalCalculatedItemDiscounts = 0.0;
            $totalCalculatedBillDiscounts = 0.0;
            $totalCalculatedCouponDiscounts = 0.0;
            $totalCalculatedRoundOff = 0.0;
            $totalCalculatedCogs = 0.0;
            $warningItems = [];

            $lines[] = '>>> COMPLETED SALES ('.$completedSales->count().' invoices):';

            if ($completedSales->isEmpty()) {
                $lines[] = '  (No completed sales found in this date range)';
            } else {
                $allSaleItemIds = $completedSales->flatMap->items->pluck('id')->filter()->values();
                $movementsBySaleItemId = InventoryMovement::whereIn('sale_item_id', $allSaleItemIds)
                    ->whereNotNull('purchase_item_id')
                    ->with(['purchaseItem.purchase'])
                    ->get()
                    ->keyBy('sale_item_id');

                $allProductIds = $completedSales->flatMap->items->pluck('product_id')->filter()->unique()->values();
                $latestPoItemsByProductId = PurchaseItem::whereIn('product_id', $allProductIds)
                    ->whereHas('purchase')
                    ->with('purchase')
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('product_id')
                    ->map(fn ($group) => $group->first());

                foreach ($completedSales as $saleIndex => $sale) {
                    $saleTotal = (float) $sale->total;
                    $saleSubtotal = (float) $sale->subtotal;
                    $saleCouponDiscount = (float) $sale->coupon_discount;
                    // In database, global_discount stores (coupon_discount - round_off).
                    // Pure bill discount (excluding coupon) is max(0, global_discount - coupon_discount):
                    $saleBillDiscount = max(0.0, round((float) $sale->global_discount - $saleCouponDiscount, 2));
                    $saleRoundOff = (float) $sale->round_off;

                    $totalCalculatedRevenue += $saleTotal;
                    $totalCalculatedSubtotal += $saleSubtotal;
                    $totalCalculatedBillDiscounts += $saleBillDiscount;
                    $totalCalculatedCouponDiscounts += $saleCouponDiscount;
                    $totalCalculatedRoundOff += $saleRoundOff;

                    $saleCogs = 0.0;
                    $itemDiscountsSum = 0.0;

                    $paymentMethodStr = $sale->payment_method instanceof BackedEnum
                        ? $sale->payment_method->value
                        : (string) ($sale->payment_method ?? 'N/A');

                    $statusStr = $sale->status instanceof BackedEnum
                        ? $sale->status->value
                        : (string) ($sale->status ?? 'completed');

                    $customerName = $sale->customer?->name ?? 'Walk-in Customer';

                    $lines[] = sprintf(
                        '  #%d Invoice: %s | ID: %d | Date: %s | Status: %s | Payment: %s | Customer: %s',
                        $saleIndex + 1,
                        $sale->invoice_number,
                        $sale->id,
                        $sale->sale_date ? $sale->sale_date->format('Y-m-d H:i:s') : 'N/A',
                        $statusStr,
                        $paymentMethodStr,
                        $customerName
                    );

                    $lines[] = sprintf(
                        '     Header -> Subtotal: INR %0.2f | Bill Disc: INR %0.2f | Coupon: INR %0.2f (%s) | Round Off: INR %0.2f | Invoice Total: INR %0.2f',
                        $saleSubtotal,
                        $saleBillDiscount,
                        $saleCouponDiscount,
                        $sale->coupon_code ?: 'none',
                        $saleRoundOff,
                        $saleTotal
                    );

                    $lines[] = '     Line Items ('.$sale->items->count().'):';

                    foreach ($sale->items as $item) {
                        $qty = (int) $item->quantity;
                        $unitPrice = (float) $item->unit_price;
                        $itemDiscount = (float) $item->discount;
                        $netUnitPrice = (float) ($item->final_price ?? ($unitPrice - $itemDiscount));
                        $lineSubtotal = (float) ($item->subtotal ?? ($netUnitPrice * $qty));
                        $itemDiscountsSum += ($itemDiscount * $qty);

                        $isService = ! empty($item->service_id);
                        $itemName = $item->item_name;
                        $code = $isService
                            ? ($item->service?->code ?? 'N/A')
                            : ($item->product?->product_code ?? $item->product?->sku ?? 'N/A');

                        $unitCost = 0.0;
                        $poOrderPrice = null;
                        $poSourceInfo = '';
                        $costSource = '';

                        if ($isService) {
                            $unitCost = 0.0;
                            $costSource = 'Service (INR 0.00 COGS)';
                        } else {
                            $mrp = (float) ($item->product?->mrp ?? $item->unit_price);
                            $productBuy = (float) ($item->product?->purchase_price ?? 0);
                            $recordedCost = (float) ($item->cost_price ?? 0);

                            $movement = $movementsBySaleItemId->get($item->id);
                            $latestPo = $latestPoItemsByProductId->get($item->product_id);

                            // Priority 1: Check FIFO deducted PO batch from inventory movements
                            if ($movement && $movement->purchaseItem && (float) $movement->purchaseItem->unit_price > 0) {
                                $poOrderPrice = (float) $movement->purchaseItem->unit_price;
                                $poRef = $movement->purchaseItem->purchase?->invoice_number ?? "PO #{$movement->purchaseItem->purchase_id}";
                                $poDate = $movement->purchaseItem->purchase?->purchase_date ? Carbon::parse($movement->purchaseItem->purchase->purchase_date)->format('Y-m-d') : '';
                                $poSourceInfo = "FIFO Batch: {$poRef}".($poDate ? " ({$poDate})" : '');
                                $unitCost = $poOrderPrice;
                                $costSource = "PO Order Price [{$poSourceInfo}]";
                            }
                            // Priority 2: Check latest PO item placed for this product
                            elseif ($latestPo && (float) $latestPo->unit_price > 0) {
                                $poOrderPrice = (float) $latestPo->unit_price;
                                $poRef = $latestPo->purchase?->invoice_number ?? "PO #{$latestPo->purchase_id}";
                                $poDate = $latestPo->purchase?->purchase_date ? Carbon::parse($latestPo->purchase->purchase_date)->format('Y-m-d') : '';
                                $poSourceInfo = "Latest PO Placed: {$poRef}".($poDate ? " ({$poDate})" : '');
                                $unitCost = $poOrderPrice;
                                $costSource = "PO Order Price [{$poSourceInfo}]";
                            }
                            // Priority 3: Check recorded cost_price if lower than MRP
                            elseif ($recordedCost > 0 && ($mrp <= 0 || $recordedCost < $mrp)) {
                                $unitCost = $recordedCost;
                                $costSource = 'Recorded HPP (No PO Item Found)';
                            }
                            // Priority 4: Check product catalog purchase price if lower than MRP
                            elseif ($productBuy > 0 && ($mrp <= 0 || $productBuy < $mrp)) {
                                $unitCost = $productBuy;
                                $costSource = 'Product Catalog Purchase Price (No PO Item Found)';
                            }
                            // Priority 5: Fallback
                            else {
                                $unitCost = $recordedCost ?: $productBuy;
                                $costSource = ($unitCost >= $mrp && $mrp > 0)
                                    ? 'MRP Fallback (No PO item found in system)'
                                    : 'Zero Cost (No PO found)';

                                if ($unitCost <= 0) {
                                    $warningItems[] = "Product '{$itemName}' in Invoice {$sale->invoice_number} has INR 0.00 cost price and no PO found.";
                                } elseif ($unitCost >= $mrp && $mrp > 0) {
                                    $warningItems[] = "Product '{$itemName}' in Invoice {$sale->invoice_number} has no PO Order Price found in system; defaulted to full MRP INR {$mrp}.";
                                }
                            }
                        }

                        $lineCogs = (float) ($qty * $unitCost);
                        $lineGrossProfit = (float) ($lineSubtotal - $lineCogs);
                        $lineMargin = $lineSubtotal > 0 ? round(($lineGrossProfit / $lineSubtotal) * 100, 1) : 0.0;
                        $saleCogs += $lineCogs;

                        $lines[] = sprintf(
                            '       * [%s] %s (Code: %s, ID: %d)',
                            $isService ? 'SERVICE' : 'PRODUCT',
                            $itemName,
                            $code,
                            $isService ? $item->service_id : $item->product_id
                        );
                        $lines[] = sprintf(
                            '         Qty: %d | Unit Price (MRP): INR %0.2f | Disc: INR %0.2f | Net Selling Price: INR %0.2f | Line Total: INR %0.2f',
                            $qty,
                            $unitPrice,
                            $itemDiscount,
                            $netUnitPrice,
                            $lineSubtotal
                        );
                        if (! $isService) {
                            if ($poOrderPrice !== null) {
                                $lines[] = sprintf(
                                    '         PO Order Price (Seller Bought Price): INR %0.2f [%s]',
                                    $poOrderPrice,
                                    $poSourceInfo
                                );
                            } else {
                                $lines[] = '         PO Order Price (Seller Bought Price): None found in PO records';
                            }
                        }
                        $lines[] = sprintf(
                            '         Cost Price Used: INR %0.2f [%s] | Line COGS: INR %0.2f | Gross Profit: INR %0.2f (Margin: %0.1f%%)',
                            $unitCost,
                            $costSource,
                            $lineCogs,
                            $lineGrossProfit,
                            $lineMargin
                        );
                    }

                    $totalCalculatedCogs += $saleCogs;
                    $totalCalculatedItemDiscounts += $itemDiscountsSum;

                    $saleGrossProfit = (float) ($saleTotal - $saleCogs);
                    $saleMargin = $saleTotal > 0 ? round(($saleGrossProfit / $saleTotal) * 100, 1) : 0.0;

                    $lines[] = sprintf(
                        '     Sale Financial Summary -> Net Revenue: INR %0.2f | Total COGS: INR %0.2f | Gross Profit: INR %0.2f (Margin: %0.1f%%)',
                        $saleTotal,
                        $saleCogs,
                        $saleGrossProfit,
                        $saleMargin
                    );
                    $lines[] = '';
                }
            }

            // Non-completed sales breakdown
            if ($nonCompletedSales->isNotEmpty()) {
                $lines[] = '>>> NON-COMPLETED SALES IN THIS DATE RANGE (EXCLUDED FROM COMPLETED KPIS):';
                foreach ($nonCompletedSales as $sale) {
                    $statusStr = $sale->status instanceof BackedEnum
                        ? $sale->status->value
                        : (string) ($sale->status ?? 'unknown');

                    $lines[] = sprintf(
                        '  ! Invoice: %s | Status: %s | Date: %s | Total: INR %0.2f [EXCLUDED from Revenue & COGS]',
                        $sale->invoice_number,
                        strtoupper($statusStr),
                        $sale->sale_date ? $sale->sale_date->format('Y-m-d H:i:s') : 'N/A',
                        (float) $sale->total
                    );
                }
                $lines[] = '';
            }

            // Finance Transactions Breakdown
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d 23:59:59');

            $financeTxns = FinanceTransaction::with('category')
                ->whereBetween('transaction_date', [$startDateStr, $endDateStr])
                ->orderBy('transaction_date')
                ->get();

            $totalIncome = 0.0;
            $totalExpense = 0.0;

            $lines[] = '>>> FINANCE TRANSACTIONS ('.$financeTxns->count().' records):';
            if ($financeTxns->isEmpty()) {
                $lines[] = '  (No finance transactions found in this date range)';
            } else {
                foreach ($financeTxns as $txnIndex => $txn) {
                    $amount = (float) $txn->amount;
                    $type = $txn->category?->type;
                    $typeStr = $type instanceof BackedEnum ? $type->value : (string) ($type ?? 'unknown');

                    if ($typeStr === 'income') {
                        $totalIncome += $amount;
                    } elseif ($typeStr === 'expense') {
                        $totalExpense += $amount;
                    }

                    $lines[] = sprintf(
                        '  #%d TXN: %s | Date: %s | Type: %s | Category: %s | Amount: INR %0.2f | Ref: %s#%s | Desc: %s',
                        $txnIndex + 1,
                        $txn->code,
                        $txn->transaction_date ? Carbon::parse($txn->transaction_date)->format('Y-m-d') : 'N/A',
                        strtoupper($typeStr),
                        $txn->category?->name ?? 'Uncategorized',
                        $amount,
                        class_basename($txn->reference_type ?? ''),
                        $txn->reference_id ?? '-',
                        $txn->description ?? '-'
                    );
                }
            }

            $overallGrossProfit = (float) ($totalCalculatedRevenue - $totalCalculatedCogs);
            $overallMargin = $totalCalculatedRevenue > 0 ? round(($overallGrossProfit / $totalCalculatedRevenue) * 100, 1) : 0.0;
            $netCashFlow = (float) ($totalIncome - $totalExpense);

            $lines[] = '';
            $lines[] = '>>> FINAL DASHBOARD KPIS FOR '.strtoupper($periodKey).':';
            $lines[] = sprintf('  * Total Completed Invoices: %d', $completedSales->count());
            $lines[] = sprintf('  * Total Revenue: INR %0.2f', $totalCalculatedRevenue);
            $lines[] = sprintf('  * Total Items Subtotal: INR %0.2f', $totalCalculatedSubtotal);
            $lines[] = sprintf('  * Total Item Discounts: INR %0.2f', $totalCalculatedItemDiscounts);
            $lines[] = sprintf('  * Total Global/Bill Discounts: INR %0.2f', $totalCalculatedBillDiscounts);
            $lines[] = sprintf('  * Total Coupon Discounts: INR %0.2f', $totalCalculatedCouponDiscounts);
            $lines[] = sprintf('  * Total Round Off: INR %0.2f', $totalCalculatedRoundOff);
            $lines[] = sprintf('  * Total COGS: INR %0.2f', $totalCalculatedCogs);
            $lines[] = sprintf('  * Gross Profit: INR %0.2f (Margin: %0.1f%%)', $overallGrossProfit, $overallMargin);
            $lines[] = sprintf('  * Cash Flow Income: INR %0.2f', $totalIncome);
            $lines[] = sprintf('  * Cash Flow Expense: INR %0.2f', $totalExpense);
            $lines[] = sprintf('  * Net Cash Flow: INR %0.2f', $netCashFlow);

            if (! empty($warningItems)) {
                $lines[] = '';
                $lines[] = '>>> WARNINGS / ALERTS:';
                foreach (array_unique($warningItems) as $warning) {
                    $lines[] = '  ! '.$warning;
                }
            }

            if (abs($totalCalculatedRevenue - $totalIncome) > 0.01) {
                $lines[] = sprintf(
                    '  ! RECONCILIATION NOTICE: Sales Revenue (INR %0.2f) != Finance Income (INR %0.2f). Difference: INR %0.2f (e.g. non-sale income, unpaid invoices, or date mismatch)',
                    $totalCalculatedRevenue,
                    $totalIncome,
                    abs($totalCalculatedRevenue - $totalIncome)
                );
            }

            $lines[] = '================================================================================';

            $report = implode(PHP_EOL, $lines);
            Log::info($report);

            return $report;
        } catch (\Throwable $e) {
            Log::error("[DASHBOARD KPI AUDIT] Failed to audit KPIs: {$e->getMessage()}", [
                'period' => $periodKey,
                'startDate' => $startDate->toDateTimeString(),
                'endDate' => $endDate->toDateTimeString(),
                'trace' => $e->getTraceAsString(),
            ]);

            return "Error during KPI audit: {$e->getMessage()}";
        }
    }

    /**
     * Get Sales Statistics (Total Revenue, Net Profit, Count)
     */
    public function getSalesStats(Carbon $startDate, Carbon $endDate, string $periodKey): array
    {
        $cacheKey = "dashboard_sales_{$periodKey}_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addSeconds(20), function () use ($startDate, $endDate) {
            $startStr = $startDate->copy()->startOfDay()->toDateTimeString();
            $endStr = $endDate->copy()->endOfDay()->toDateTimeString();

            // Optimize: Use aggregate queries instead of loading all models into memory
            $salesData = Sale::whereBetween('sale_date', [$startStr, $endStr])
                ->where('status', 'completed')
                ->selectRaw('COUNT(*) as count, SUM(total) as total_revenue, SUM(coupon_discount) as total_coupon_discount')
                ->first();

            $totalRevenue = (float) ($salesData->total_revenue ?? 0);
            $count = (int) ($salesData->count ?? 0);
            $totalCouponDiscount = (float) ($salesData->total_coupon_discount ?? 0);

            // COGS = Sum of (Sales Item Quantity * Cost Price) for physical products only.
            // Priority:
            // 1. PO Order price from deducted FIFO movement
            // 2. PO Order price from latest PO item placed for this product
            // 3. Recorded cost_price if < MRP
            // 4. Product catalog purchase_price if < MRP
            // 5. Fallback recorded cost or purchase_price
            $saleItems = SaleItem::query()
                ->whereHas('sale', function ($query) use ($startStr, $endStr) {
                    $query->whereBetween('sale_date', [$startStr, $endStr])
                        ->where('status', 'completed');
                })
                ->whereNotNull('product_id')
                ->with(['product:id,mrp,purchase_price'])
                ->get();

            $saleItemIds = $saleItems->pluck('id')->filter()->values();
            $movementsBySaleItemId = InventoryMovement::whereIn('sale_item_id', $saleItemIds)
                ->whereNotNull('purchase_item_id')
                ->with('purchaseItem')
                ->get()
                ->keyBy('sale_item_id');

            $productIds = $saleItems->pluck('product_id')->filter()->unique()->values();
            $latestPoItemsByProductId = PurchaseItem::whereIn('product_id', $productIds)
                ->whereHas('purchase')
                ->orderByDesc('id')
                ->get(['id', 'product_id', 'unit_price', 'purchase_id'])
                ->groupBy('product_id')
                ->map(fn ($group) => (float) $group->first()->unit_price);

            $cogs = 0.0;
            foreach ($saleItems as $item) {
                $qty = (int) $item->quantity;
                $mrp = (float) ($item->product?->mrp ?? 0);
                $productBuy = (float) ($item->product?->purchase_price ?? 0);
                $recordedCost = (float) ($item->cost_price ?? 0);

                $movement = $movementsBySaleItemId->get($item->id);
                $latestPoPrice = $latestPoItemsByProductId->get($item->product_id);

                if ($movement && $movement->purchaseItem && (float) $movement->purchaseItem->unit_price > 0) {
                    $unitCost = (float) $movement->purchaseItem->unit_price;
                } elseif ($latestPoPrice !== null && $latestPoPrice > 0) {
                    $unitCost = $latestPoPrice;
                } elseif ($recordedCost > 0 && ($mrp <= 0 || $recordedCost < $mrp)) {
                    $unitCost = $recordedCost;
                } elseif ($productBuy > 0 && ($mrp <= 0 || $productBuy < $mrp)) {
                    $unitCost = $productBuy;
                } else {
                    $unitCost = $recordedCost ?: $productBuy;
                }

                $cogs += ($qty * $unitCost);
            }

            $grossProfit = (float) ($totalRevenue - $cogs);
            $margin = $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 1) : 0.0;

            return [
                'total_revenue' => $totalRevenue,
                'count' => $count,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'margin' => $margin,
                'coupon_discount' => $totalCouponDiscount,
            ];
        });
    }

    /**
     * Get Cash Flow Statistics (Income, Expense, Net)
     */
    public function getCashFlowStats(Carbon $startDate, Carbon $endDate, string $periodKey): array
    {
        $cacheKey = "dashboard_cashflow_{$periodKey}_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addSeconds(20), function () use ($startDate, $endDate) {
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d 23:59:59');

            // Optimize: Calculate Income and Expense directly in the DB using joins
            $totals = FinanceTransaction::join('finance_categories', 'finance_transactions.finance_category_id', '=', 'finance_categories.id')
                ->whereBetween('finance_transactions.transaction_date', [$startDateStr, $endDateStr])
                ->selectRaw('finance_categories.type, SUM(finance_transactions.amount) as total')
                ->groupBy('finance_categories.type')
                ->pluck('total', 'finance_categories.type');

            $income = (float) ($totals['income'] ?? 0);
            $expense = (float) ($totals['expense'] ?? 0);

            return [
                'income' => $income,
                'expense' => $expense,
                'net_cash_flow' => (float) ($income - $expense),
            ];
        });
    }

    /**
     * Get Low Stock Products
     */
    public function getLowStockProducts(?int $limit = null): array
    {
        // Cache for 5 minutes as stock levels change frequently.
        return Cache::remember('dashboard_low_stock', now()->addSeconds(20), function () use ($limit) {
            $query = Product::query()
                ->select(['id', 'sku', 'name', 'quantity', 'min_stock'])
                ->whereColumn('quantity', '<=', 'min_stock')
                ->where('is_active', true)
                ->orderBy('quantity', 'asc')
                ->orderBy('name');

            if ($limit) {
                $query->limit($limit);
            }

            return $query->get()
                ->toArray();
        });
    }

    public function getLowStockCount(): int
    {
        return Cache::remember('dashboard_low_stock_count', now()->addSeconds(20), function () {
            return Product::query()
                ->whereColumn('quantity', '<=', 'min_stock')
                ->where('is_active', true)
                ->count();
        });
    }

    /**
     * Get Top Selling Products
     */
    public function getTopProducts(Carbon $startDate, Carbon $endDate, int $limit = 5): array
    {
        $cacheKey = "dashboard_top_products_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addSeconds(20), function () use ($startDate, $endDate, $limit) {
            $startStr = $startDate->copy()->startOfDay()->toDateTimeString();
            $endStr = $endDate->copy()->endOfDay()->toDateTimeString();

            return SaleItem::select('product_id', DB::raw('SUM(quantity) as total_qty'))
                ->whereNotNull('product_id')
                ->whereHas('sale', function ($query) use ($startStr, $endStr) {
                    $query->whereBetween('sale_date', [$startStr, $endStr])
                        ->where('status', 'completed');
                })
                ->whereHas('product')
                ->with('product:id,name,sku')
                ->groupBy('product_id')
                ->orderByDesc('total_qty')
                ->limit($limit)
                ->get()
                ->filter(fn ($item) => $item->product !== null)
                ->map(function ($item) {
                    return [
                        'product_name' => $item->product->name ?? 'Unknown',
                        'sku' => $item->product->sku ?? '—',
                        'total_sold' => (int) $item->total_qty,
                    ];
                })
                ->values()
                ->toArray();
        });
    }

    /**
     * Get Recent Sales
     */
    public function getRecentSales(int $limit = 5): array
    {
        return Cache::remember('dashboard_recent_sales', now()->addSeconds(20), function () use ($limit) {
            return Sale::with('customer:id,name')
                ->orderByDesc('sale_date')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get Sales Chart Data (Daily Trend)
     */
    public function getSalesTrend(Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "dashboard_sales_trend_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addSeconds(20), function () use ($startDate, $endDate) {
            $startStr = $startDate->copy()->startOfDay()->toDateTimeString();
            $endStr = $endDate->copy()->endOfDay()->toDateTimeString();

            $data = Sale::selectRaw('DATE(sale_date) as date, SUM(total) as total')
                ->whereBetween('sale_date', [$startStr, $endStr])
                ->where('status', 'completed')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('total', 'date')
                ->toArray();

            $daysDiff = $startDate->diffInDays($endDate);
            if ($daysDiff > 120) {
                return $data;
            }

            // Fill missing dates with 0
            $period = CarbonPeriod::create($startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
            $chartData = [];

            foreach ($period as $date) {
                $formattedDate = $date->format('Y-m-d');
                $chartData[$formattedDate] = (float) ($data[$formattedDate] ?? 0);
            }

            return $chartData;
        });
    }

    /**
     * Get Cash Flow Chart Data (Income vs Expense)
     */
    public function getCashFlowTrend(Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "dashboard_cashflow_trend_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addSeconds(20), function () use ($startDate, $endDate) {
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d 23:59:59');

            // Optimize: Group by date and type at the database level instead of memory
            $transactions = FinanceTransaction::join('finance_categories', 'finance_transactions.finance_category_id', '=', 'finance_categories.id')
                ->whereBetween('finance_transactions.transaction_date', [$startDateStr, $endDateStr])
                ->selectRaw('DATE(finance_transactions.transaction_date) as date, finance_categories.type, SUM(finance_transactions.amount) as total')
                ->groupBy('date', 'finance_categories.type')
                ->get();

            // Structure data
            $grouped = [];
            foreach ($transactions as $t) {
                $grouped[$t->date][$t->type] = $t->total;
            }

            $daysDiff = $startDate->diffInDays($endDate);
            if ($daysDiff > 120) {
                $incomeData = [];
                $expenseData = [];
                foreach ($grouped as $dateKey => $types) {
                    $incomeData[$dateKey] = (float) ($types['income'] ?? 0);
                    $expenseData[$dateKey] = (float) ($types['expense'] ?? 0);
                }

                return [
                    'income' => $incomeData,
                    'expense' => $expenseData,
                ];
            }

            // Fill missing dates
            $period = CarbonPeriod::create($startDateStr, $endDateStr);
            $incomeData = [];
            $expenseData = [];

            foreach ($period as $date) {
                $formattedDate = $date->format('Y-m-d');
                $incomeData[$formattedDate] = (float) ($grouped[$formattedDate]['income'] ?? 0);
                $expenseData[$formattedDate] = (float) ($grouped[$formattedDate]['expense'] ?? 0);
            }

            return [
                'income' => $incomeData,
                'expense' => $expenseData,
            ];
        });
    }

    /**
     * Get Top Customers by Revenue
     */
    public function getTopCustomers(Carbon $startDate, Carbon $endDate, int $limit = 5): array
    {
        $cacheKey = "dashboard_top_customers_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addSeconds(20), function () use ($startDate, $endDate, $limit) {
            $startStr = $startDate->copy()->startOfDay()->toDateTimeString();
            $endStr = $endDate->copy()->endOfDay()->toDateTimeString();

            return Sale::select('customer_id', DB::raw('SUM(total) as total_spent'))
                ->whereBetween('sale_date', [$startStr, $endStr])
                ->where('status', 'completed')
                ->whereNotNull('customer_id')
                ->with('customer:id,name,phone')
                ->groupBy('customer_id')
                ->orderByDesc('total_spent')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'customer_name' => $item->customer->name ?? 'Unknown',
                        'phone' => format_indian_phone($item->customer->phone),
                        'total_spent' => (float) $item->total_spent,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get Expense Breakdown by Category
     */
    public function getExpenseBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "dashboard_expense_breakdown_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addSeconds(20), function () use ($startDate, $endDate) {
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d 23:59:59');

            return FinanceTransaction::select('finance_category_id', DB::raw('SUM(amount) as total_amount'))
                ->whereBetween('transaction_date', [$startDateStr, $endDateStr])
                ->whereHas('category', function ($query) {
                    $query->where('type', 'expense');
                })
                ->with('category:id,name,type')
                ->groupBy('finance_category_id')
                ->orderByDesc('total_amount')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_name' => $item->category->name ?? 'Uncategorized',
                        'total_amount' => (float) $item->total_amount,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Clear all cached dashboard statistics across all periods.
     */
    public function clearDashboardCache(): void
    {
        Cache::forget('dashboard_recent_sales');
        Cache::forget('dashboard_low_stock');
        Cache::forget('dashboard_low_stock_count');

        // Flush application cache to ensure all dynamic date keys (today, yesterday, this_month, custom, etc.) are purged
        Cache::flush();
    }
}
