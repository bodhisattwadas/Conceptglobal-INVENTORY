<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportService
{
    /**
     * Get aggregated reporting data for the given date window.
     *
     * @return array{
     *     kpis: array{
     *         net_sales: float,
     *         gross_profit: float,
     *         cogs: float,
     *         margin: float,
     *         gross_sales: float,
     *         total_discounts: float,
     *         total_orders: int,
     *         average_order_value: float
     *     },
     *     payment_methods: array<string, array{
     *         key: string,
     *         label: string,
     *         amount: float,
     *         count: int,
     *         percentage: float
     *     }>,
     *     daily_breakdown: Collection,
     *     brand_breakdown: Collection,
     *     sales: Collection
     * }
     */
    public function getReportData(Carbon $startDate, Carbon $endDate): array
    {
        $sales = Sale::query()
            ->with([
                'customer',
                'creator',
                'items.product.company',
                'items.service',
                'items.inventoryMovements.purchaseItem.purchase',
            ])
            ->where('status', SaleStatus::COMPLETED)
            ->whereBetween('sale_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->orderBy('sale_date', 'desc')
            ->get();

        $this->attachCogsAndProfitToSales($sales);

        $kpis = $this->calculateKpis($sales);
        $paymentMethods = $this->calculatePaymentMethodBreakdown($sales, $kpis['net_sales']);
        $dailyBreakdown = $this->calculateDailyBreakdown($sales);
        $brandBreakdown = $this->calculateBrandBreakdown($sales, $kpis['net_sales']);
        $itemBreakdown = $this->calculateItemBreakdown($sales, $kpis['net_sales']);

        return [
            'kpis' => $kpis,
            'payment_methods' => $paymentMethods,
            'daily_breakdown' => $dailyBreakdown,
            'brand_breakdown' => $brandBreakdown,
            'item_breakdown' => $itemBreakdown,
            'sales' => $sales,
        ];
    }

    /**
     * Compute and attach COGS, gross profit, and margin to each sale.
     * Consistent with Dashboard calculation logic.
     */
    protected function attachCogsAndProfitToSales(Collection $sales): void
    {
        $productIds = $sales
            ->flatMap(fn ($sale) => $sale->items)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $latestPoItemsByProductId = $productIds->isNotEmpty()
            ? PurchaseItem::whereIn('product_id', $productIds)
                ->whereHas('purchase', fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->where('unit_price', '>', 0)
                ->orderByDesc('id')
                ->get(['id', 'product_id', 'unit_price', 'purchase_id'])
                ->groupBy('product_id')
                ->map(fn ($group) => (float) $group->first()->unit_price)
            : collect();

        foreach ($sales as $sale) {
            $saleCogs = 0.0;
            $hasOnlyServices = true;
            $hasAnyPhysicalProducts = false;

            foreach ($sale->items as $item) {
                if ($item->product_id !== null && empty($item->service_id)) {
                    $hasOnlyServices = false;
                    $hasAnyPhysicalProducts = true;
                }
                $itemCogs = $this->calculateItemCogs($item, $latestPoItemsByProductId);
                $saleCogs += $itemCogs;
            }

            $sale->cogs = round($saleCogs, 2);
            $sale->has_only_services = $hasOnlyServices && $sale->items->isNotEmpty();
            $sale->has_physical_products = $hasAnyPhysicalProducts;

            $netRevenue = (float) $sale->total;
            $grossProfit = round($netRevenue - $sale->cogs, 2);
            $sale->gross_profit = $grossProfit;

            $margin = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 1) : 0.0;
            if (abs($margin) < 0.05) {
                $margin = 0.0;
            }
            $sale->margin = $margin;
        }
    }

    /**
     * Calculate single line item COGS following FIFO and Dashboard priority logic.
     * Net price is the PO vendor buying price (MRP - vendor discount).
     */
    protected function calculateItemCogs(SaleItem $item, Collection $latestPoItemsByProductId): float
    {
        // Services have 0 inventory cost
        if ($item->product_id === null || ! empty($item->service_id)) {
            $item->po_unit_price = 0.0;
            $item->po_cost_total = 0.0;
            $item->po_source = 'Service (No inventory cost)';

            return 0.0;
        }

        $qty = (int) $item->quantity;
        $mrp = (float) ($item->product?->mrp ?: $item->unit_price);
        $productBuy = (float) ($item->product?->purchase_price ?? 0);
        $recordedCost = (float) ($item->cost_price ?? 0);
        $latestPoPrice = $latestPoItemsByProductId->get($item->product_id);

        // Priority 1: Check FIFO deducted PO batch from inventory movements
        $deductions = $item->inventoryMovements
            ? $item->inventoryMovements->filter(
                fn ($m) => $m->purchase_item_id !== null
                    && $m->purchaseItem
                    && (float) $m->purchaseItem->unit_price > 0
                    && $m->quantity < 0
            )
            : collect();

        if ($deductions->isNotEmpty()) {
            $movementCogs = 0.0;
            $movementQty = 0;
            $sources = [];

            foreach ($deductions as $m) {
                $take = abs((int) $m->quantity);
                $pPrice = (float) $m->purchaseItem->unit_price;
                $movementCogs += ($take * $pPrice);
                $movementQty += $take;
                $ref = $m->purchaseItem->purchase?->invoice_number ?: "PO #{$m->purchaseItem->purchase_id}";
                $sources[] = "{$take}x @ ₹".number_format($pPrice, 2)." ({$ref})";
            }

            if ($movementQty >= $qty) {
                $totalCogs = round($movementCogs, 2);
                $item->po_unit_price = $qty > 0 ? round($totalCogs / $qty, 2) : 0.0;
                $item->po_cost_total = $totalCogs;
                $item->po_source = implode(', ', $sources);

                return (float) $totalCogs;
            }

            // Partially covered by FIFO; cover remainder with fallback
            $remainingQty = $qty - $movementQty;
            $unitCost = $latestPoPrice
                ?: ($recordedCost > 0 && ($mrp <= 0 || $recordedCost < $mrp) ? $recordedCost
                : ($productBuy > 0 && ($mrp <= 0 || $productBuy < $mrp) ? $productBuy
                : ($recordedCost ?: $productBuy)));

            $totalCogs = round($movementCogs + ($remainingQty * $unitCost), 2);
            $item->po_unit_price = $qty > 0 ? round($totalCogs / $qty, 2) : 0.0;
            $item->po_cost_total = $totalCogs;
            $item->po_source = implode(', ', $sources)." + {$remainingQty}x @ ₹".number_format($unitCost, 2);

            return (float) $totalCogs;
        }

        // Priority 2: Latest PO item placed for this product (MRP - vendor discount)
        if ($latestPoPrice !== null && $latestPoPrice > 0) {
            $unitCost = $latestPoPrice;
            $source = 'Latest PO (MRP - vendor discount)';
        }
        // Priority 3: Recorded cost_price if lower than MRP
        elseif ($recordedCost > 0 && ($mrp <= 0 || $recordedCost < $mrp)) {
            $unitCost = $recordedCost;
            $source = 'Recorded cost price';
        }
        // Priority 4: Product catalog purchase price if lower than MRP
        elseif ($productBuy > 0 && ($mrp <= 0 || $productBuy < $mrp)) {
            $unitCost = $productBuy;
            $source = 'Catalog purchase price';
        }
        // Priority 5: Fallback to recorded cost or catalog purchase price
        else {
            $unitCost = $recordedCost ?: $productBuy;
            $source = 'Catalog fallback price';
        }

        $totalCogs = round($qty * $unitCost, 2);
        $item->po_unit_price = round($unitCost, 2);
        $item->po_cost_total = $totalCogs;
        $item->po_source = $source;

        return (float) $totalCogs;
    }

    /**
     * Calculate core KPI metrics from sales collection including profit and margin.
     */
    protected function calculateKpis(Collection $sales): array
    {
        $totalOrders = $sales->count();
        $grossSales = (float) $sales->sum(fn ($sale) => (float) $sale->subtotal);
        $netSales = (float) $sales->sum(fn ($sale) => (float) $sale->total);
        $totalCogs = (float) $sales->sum(fn ($sale) => (float) ($sale->cogs ?? 0));
        $grossProfit = (float) ($netSales - $totalCogs);
        $margin = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 1) : 0.0;
        if (abs($margin) < 0.05) {
            $margin = 0.0;
        }

        // In database, total_discount already includes item discounts, coupons, and bill discounts
        $totalDiscounts = (float) $sales->sum(fn ($sale) => (float) ($sale->total_discount ?? 0));

        $aov = $totalOrders > 0 ? round($netSales / $totalOrders, 2) : 0.0;

        return [
            'net_sales' => round($netSales, 2),
            'cogs' => round($totalCogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'margin' => $margin,
            'gross_sales' => round($grossSales, 2),
            'total_discounts' => round($totalDiscounts, 2),
            'total_orders' => $totalOrders,
            'average_order_value' => $aov,
        ];
    }

    /**
     * Calculate payment method breakdown with amounts, counts, and revenue shares.
     */
    protected function calculatePaymentMethodBreakdown(Collection $sales, float $totalNetSales): array
    {
        $methods = [
            PaymentMethod::CASH->value => [
                'key' => PaymentMethod::CASH->value,
                'label' => PaymentMethod::CASH->label(),
                'amount' => 0.0,
                'count' => 0,
                'percentage' => 0.0,
            ],
            PaymentMethod::UPI->value => [
                'key' => PaymentMethod::UPI->value,
                'label' => PaymentMethod::UPI->label(),
                'amount' => 0.0,
                'count' => 0,
                'percentage' => 0.0,
            ],
            PaymentMethod::CARD->value => [
                'key' => PaymentMethod::CARD->value,
                'label' => PaymentMethod::CARD->label(),
                'amount' => 0.0,
                'count' => 0,
                'percentage' => 0.0,
            ],
            PaymentMethod::TRANSFER->value => [
                'key' => PaymentMethod::TRANSFER->value,
                'label' => PaymentMethod::TRANSFER->label(),
                'amount' => 0.0,
                'count' => 0,
                'percentage' => 0.0,
            ],
        ];

        foreach ($sales as $sale) {
            $val = $sale->payment_method instanceof PaymentMethod
                ? $sale->payment_method->value
                : (string) $sale->payment_method;

            if (! isset($methods[$val])) {
                $methods[$val] = [
                    'key' => $val,
                    'label' => ucfirst($val),
                    'amount' => 0.0,
                    'count' => 0,
                    'percentage' => 0.0,
                ];
            }

            $methods[$val]['amount'] += (float) $sale->total;
            $methods[$val]['count'] += 1;
        }

        foreach ($methods as $key => $data) {
            $methods[$key]['amount'] = round($data['amount'], 2);
            $methods[$key]['percentage'] = $totalNetSales > 0
                ? round(($data['amount'] / $totalNetSales) * 100, 1)
                : 0.0;
        }

        return $methods;
    }

    /**
     * Calculate day-by-day sales, collections, and profit distribution.
     */
    protected function calculateDailyBreakdown(Collection $sales): Collection
    {
        return $sales
            ->groupBy(fn ($sale) => $sale->sale_date ? $sale->sale_date->format('Y-m-d') : 'Unknown')
            ->map(function (Collection $daySales, string $date) {
                $gross = (float) $daySales->sum(fn ($s) => (float) $s->subtotal);
                $discounts = (float) $daySales->sum(fn ($s) => (float) ($s->total_discount ?? 0));
                $net = (float) $daySales->sum(fn ($s) => (float) $s->total);
                $cogs = (float) $daySales->sum(fn ($s) => (float) ($s->cogs ?? 0));
                $profit = (float) ($net - $cogs);
                $margin = $net > 0 ? round(($profit / $net) * 100, 1) : 0.0;
                if (abs($margin) < 0.05) {
                    $margin = 0.0;
                }

                $cash = (float) $daySales->where(function ($s) {
                    $val = $s->payment_method instanceof PaymentMethod ? $s->payment_method->value : $s->payment_method;

                    return $val === PaymentMethod::CASH->value;
                })->sum('total');

                $upi = (float) $daySales->where(function ($s) {
                    $val = $s->payment_method instanceof PaymentMethod ? $s->payment_method->value : $s->payment_method;

                    return $val === PaymentMethod::UPI->value;
                })->sum('total');

                $card = (float) $daySales->where(function ($s) {
                    $val = $s->payment_method instanceof PaymentMethod ? $s->payment_method->value : $s->payment_method;

                    return $val === PaymentMethod::CARD->value;
                })->sum('total');

                $transfer = (float) $daySales->where(function ($s) {
                    $val = $s->payment_method instanceof PaymentMethod ? $s->payment_method->value : $s->payment_method;

                    return $val === PaymentMethod::TRANSFER->value;
                })->sum('total');

                $formattedDate = $date !== 'Unknown'
                    ? Carbon::parse($date)->format('d M Y')
                    : 'Unknown';

                return [
                    'date' => $date,
                    'formatted_date' => $formattedDate,
                    'orders_count' => $daySales->count(),
                    'gross_sales' => round($gross, 2),
                    'total_discounts' => round($discounts, 2),
                    'net_sales' => round($net, 2),
                    'cogs' => round($cogs, 2),
                    'gross_profit' => round($profit, 2),
                    'margin' => $margin,
                    'cash' => round($cash, 2),
                    'upi' => round($upi, 2),
                    'card' => round($card, 2),
                    'transfer' => round($transfer, 2),
                ];
            })
            ->values();
    }

    /**
     * Calculate sales, profit, and item-by-item breakdown grouped by Brand (Company).
     *
     * @return Collection<int, array{
     *     key: string,
     *     name: string,
     *     is_service: bool,
     *     items_count: int,
     *     total_quantity: int,
     *     gross_sales: float,
     *     total_discounts: float,
     *     net_sales: float,
     *     cogs: float,
     *     gross_profit: float,
     *     margin: float,
     *     percentage: float,
     *     items: Collection
     * }>
     */
    protected function calculateBrandBreakdown(Collection $sales, float $totalNetSales): Collection
    {
        $brandsData = [];

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                if ($item->isProduct()) {
                    $company = $item->product?->company;
                    $brandKey = $company ? 'brand_'.$company->id : 'brand_unbranded';
                    $brandName = $company ? ($company->short_name ?: $company->company_name) : 'Unbranded / Other';
                    $isService = false;
                } elseif ($item->isService()) {
                    $brandKey = 'brand_services';
                    $brandName = 'Salon Services';
                    $isService = true;
                } else {
                    $brandKey = 'brand_manual';
                    $brandName = 'Manual / Unreconciled Items';
                    $isService = false;
                }

                if (! isset($brandsData[$brandKey])) {
                    $brandsData[$brandKey] = [
                        'key' => $brandKey,
                        'name' => $brandName,
                        'is_service' => $isService,
                        'total_quantity' => 0,
                        'gross_sales' => 0.0,
                        'total_discounts' => 0.0,
                        'net_sales' => 0.0,
                        'cogs' => 0.0,
                        'gross_profit' => 0.0,
                        'margin' => 0.0,
                        'percentage' => 0.0,
                        'items' => [],
                    ];
                }

                $qty = (int) $item->quantity;
                $gross = (float) ($item->unit_price * $qty);
                $discount = (float) ($item->discount * $qty);
                $net = (float) $item->subtotal;
                $cogs = (float) ($item->po_cost_total ?? 0.0);
                $profit = (float) ($net - $cogs);

                $brandsData[$brandKey]['total_quantity'] += $qty;
                $brandsData[$brandKey]['gross_sales'] += $gross;
                $brandsData[$brandKey]['total_discounts'] += $discount;
                $brandsData[$brandKey]['net_sales'] += $net;
                $brandsData[$brandKey]['cogs'] += $cogs;
                $brandsData[$brandKey]['gross_profit'] += $profit;

                // Group individual items under this brand
                $itemKey = $item->isProduct() ? 'prod_'.$item->product_id : ($item->isService() ? 'serv_'.$item->service_id : 'manual_'.$item->id);
                $itemName = $item->item_name;
                $itemCode = $item->isProduct()
                    ? ($item->product?->product_code ?? $item->product?->sku ?? '-')
                    : ($item->isService() ? ($item->service?->code ?? '-') : ($item->barcode ?: '-'));

                if (! isset($brandsData[$brandKey]['items'][$itemKey])) {
                    $brandsData[$brandKey]['items'][$itemKey] = [
                        'key' => $itemKey,
                        'name' => $itemName,
                        'code' => $itemCode,
                        'is_service' => $isService,
                        'quantity' => 0,
                        'unit_price' => (float) $item->unit_price,
                        'gross_sales' => 0.0,
                        'total_discounts' => 0.0,
                        'net_sales' => 0.0,
                        'cogs' => 0.0,
                        'gross_profit' => 0.0,
                        'margin' => 0.0,
                        'invoices_count' => 0,
                        'invoices' => [],
                        'po_unit_price' => (float) ($item->po_unit_price ?? 0.0),
                        'po_source' => $item->po_source ?? '-',
                    ];
                }

                $brandsData[$brandKey]['items'][$itemKey]['quantity'] += $qty;
                $brandsData[$brandKey]['items'][$itemKey]['gross_sales'] += $gross;
                $brandsData[$brandKey]['items'][$itemKey]['total_discounts'] += $discount;
                $brandsData[$brandKey]['items'][$itemKey]['net_sales'] += $net;
                $brandsData[$brandKey]['items'][$itemKey]['cogs'] += $cogs;
                $brandsData[$brandKey]['items'][$itemKey]['gross_profit'] += $profit;
                if (! in_array($sale->invoice_number, $brandsData[$brandKey]['items'][$itemKey]['invoices'], true)) {
                    $brandsData[$brandKey]['items'][$itemKey]['invoices'][] = $sale->invoice_number;
                    $brandsData[$brandKey]['items'][$itemKey]['invoices_count']++;
                }
            }
        }

        // Format and sort brands & items
        return collect($brandsData)->map(function ($brand) use ($totalNetSales) {
            $brand['gross_sales'] = round($brand['gross_sales'], 2);
            $brand['total_discounts'] = round($brand['total_discounts'], 2);
            $brand['net_sales'] = round($brand['net_sales'], 2);
            $brand['cogs'] = round($brand['cogs'], 2);
            $brand['gross_profit'] = round($brand['gross_profit'], 2);
            $margin = $brand['net_sales'] > 0 ? round(($brand['gross_profit'] / $brand['net_sales']) * 100, 1) : 0.0;
            if (abs($margin) < 0.05) {
                $margin = 0.0;
            }
            $brand['margin'] = $margin;
            $brand['percentage'] = $totalNetSales > 0 ? round(($brand['net_sales'] / $totalNetSales) * 100, 1) : 0.0;
            $brand['items_count'] = count($brand['items']);

            // Sort items by net sales descending
            $brand['items'] = collect($brand['items'])->map(function ($item) {
                $item['gross_sales'] = round($item['gross_sales'], 2);
                $item['total_discounts'] = round($item['total_discounts'], 2);
                $item['net_sales'] = round($item['net_sales'], 2);
                $item['cogs'] = round($item['cogs'], 2);
                $item['gross_profit'] = round($item['gross_profit'], 2);
                $itemMargin = $item['net_sales'] > 0 ? round(($item['gross_profit'] / $item['net_sales']) * 100, 1) : 0.0;
                if (abs($itemMargin) < 0.05) {
                    $itemMargin = 0.0;
                }
                $item['margin'] = $itemMargin;

                return $item;
            })->sortByDesc('net_sales')->values();

            return $brand;
        })->sortByDesc('net_sales')->values();
    }

    /**
     * Group sales by individual item (product or service) across all brands.
     *
     * @return Collection<int, array{
     *     key: string,
     *     name: string,
     *     code: string,
     *     brand_name: string,
     *     brand_key: string,
     *     is_service: bool,
     *     quantity: int,
     *     unit_price: float,
     *     gross_sales: float,
     *     total_discounts: float,
     *     net_sales: float,
     *     cogs: float,
     *     gross_profit: float,
     *     margin: float,
     *     invoices_count: int,
     *     invoices: array<string>,
     *     po_unit_price: float,
     *     po_source: string
     * }>
     */
    public function calculateItemBreakdown(Collection $sales, float $totalNetSales): Collection
    {
        $itemsData = [];

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $isProduct = $item->isProduct();
                $isService = $item->isService();
                $itemKey = $isProduct ? 'prod_'.$item->product_id : ($isService ? 'serv_'.$item->service_id : 'manual_'.$item->id);
                $itemName = $item->item_name;
                $itemCode = $isProduct
                    ? ($item->product?->product_code ?? $item->product?->sku ?? '-')
                    : ($isService ? ($item->service?->code ?? '-') : ($item->barcode ?: '-'));

                $brandName = 'Unbranded / Other';
                $brandKey = 'unbranded';
                if ($isProduct && $item->product && $item->product->company) {
                    $brandName = $item->product->company->short_name ?: $item->product->company->company_name;
                    $brandKey = 'brand_'.$item->product->company_id;
                } elseif ($isService) {
                    $brandName = 'Salon Services';
                    $brandKey = 'brand_services';
                } elseif ($item->isManual()) {
                    $brandName = 'Manual / Unreconciled';
                    $brandKey = 'brand_manual';
                }

                if (! isset($itemsData[$itemKey])) {
                    $itemsData[$itemKey] = [
                        'key' => $itemKey,
                        'name' => $itemName,
                        'code' => $itemCode,
                        'brand_name' => $brandName,
                        'brand_key' => $brandKey,
                        'is_service' => $isService,
                        'quantity' => 0,
                        'unit_price' => (float) $item->unit_price,
                        'gross_sales' => 0.0,
                        'total_discounts' => 0.0,
                        'net_sales' => 0.0,
                        'cogs' => 0.0,
                        'gross_profit' => 0.0,
                        'margin' => 0.0,
                        'invoices_count' => 0,
                        'invoices' => [],
                        'invoice_breakdown' => [],
                        'po_unit_price' => (float) ($item->po_unit_price ?? 0.0),
                        'po_source' => $item->po_source ?? '-',
                    ];
                }

                $qty = (int) $item->quantity;
                $gross = (float) ($item->unit_price * $qty);
                $discount = (float) ($item->discount * $qty);
                $net = (float) $item->subtotal;
                $cogs = (float) ($item->po_cost_total ?? 0.0);
                $profit = (float) ($net - $cogs);

                $itemsData[$itemKey]['quantity'] += $qty;
                $itemsData[$itemKey]['gross_sales'] += $gross;
                $itemsData[$itemKey]['total_discounts'] += $discount;
                $itemsData[$itemKey]['net_sales'] += $net;
                $itemsData[$itemKey]['cogs'] += $cogs;
                $itemsData[$itemKey]['gross_profit'] += $profit;

                if (! in_array($sale->invoice_number, $itemsData[$itemKey]['invoices'], true)) {
                    $itemsData[$itemKey]['invoices'][] = $sale->invoice_number;
                    $itemsData[$itemKey]['invoices_count']++;
                }

                $invKey = $sale->id;
                if (! isset($itemsData[$itemKey]['invoice_breakdown'][$invKey])) {
                    $methodVal = $sale->payment_method instanceof PaymentMethod
                        ? $sale->payment_method->value
                        : (string) $sale->payment_method;
                    $methodLabel = $sale->payment_method instanceof PaymentMethod
                        ? $sale->payment_method->label()
                        : ucfirst((string) $sale->payment_method);

                    $itemsData[$itemKey]['invoice_breakdown'][$invKey] = [
                        'sale_id' => $sale->id,
                        'invoice_number' => $sale->invoice_number,
                        'sale_date' => $sale->sale_date ? $sale->sale_date->format('d M Y, h:i A') : '-',
                        'customer_name' => $sale->customer?->name ?? 'Walk-in Customer',
                        'customer_phone' => $sale->customer?->phone ?? null,
                        'payment_method' => $methodVal,
                        'payment_label' => $methodLabel,
                        'quantity' => 0,
                        'unit_price' => round((float) $item->unit_price, 2),
                        'gross' => 0.0,
                        'discount' => 0.0,
                        'net_received' => 0.0,
                        'po_unit_price' => round((float) ($item->po_unit_price ?? 0.0), 2),
                        'cogs' => 0.0,
                        'gross_profit' => 0.0,
                        'margin' => 0.0,
                        'po_source' => $item->po_source ?? '-',
                        'invoice_total' => round((float) $sale->total, 2),
                    ];
                }

                $itemsData[$itemKey]['invoice_breakdown'][$invKey]['quantity'] += $qty;
                $itemsData[$itemKey]['invoice_breakdown'][$invKey]['gross'] = round($itemsData[$itemKey]['invoice_breakdown'][$invKey]['gross'] + $gross, 2);
                $itemsData[$itemKey]['invoice_breakdown'][$invKey]['discount'] = round($itemsData[$itemKey]['invoice_breakdown'][$invKey]['discount'] + $discount, 2);
                $itemsData[$itemKey]['invoice_breakdown'][$invKey]['net_received'] = round($itemsData[$itemKey]['invoice_breakdown'][$invKey]['net_received'] + $net, 2);
                $itemsData[$itemKey]['invoice_breakdown'][$invKey]['cogs'] = round($itemsData[$itemKey]['invoice_breakdown'][$invKey]['cogs'] + $cogs, 2);
                $itemsData[$itemKey]['invoice_breakdown'][$invKey]['gross_profit'] = round($itemsData[$itemKey]['invoice_breakdown'][$invKey]['gross_profit'] + $profit, 2);
                $invNet = $itemsData[$itemKey]['invoice_breakdown'][$invKey]['net_received'];
                $invProfit = $itemsData[$itemKey]['invoice_breakdown'][$invKey]['gross_profit'];
                $invMargin = $invNet > 0 ? round(($invProfit / $invNet) * 100, 1) : 0.0;
                if (abs($invMargin) < 0.05) {
                    $invMargin = 0.0;
                }
                $itemsData[$itemKey]['invoice_breakdown'][$invKey]['margin'] = $invMargin;
            }
        }

        return collect($itemsData)->map(function ($item) {
            $item['gross_sales'] = round($item['gross_sales'], 2);
            $item['total_discounts'] = round($item['total_discounts'], 2);
            $item['net_sales'] = round($item['net_sales'], 2);
            $item['cogs'] = round($item['cogs'], 2);
            $item['gross_profit'] = round($item['gross_profit'], 2);
            $margin = $item['net_sales'] > 0 ? round(($item['gross_profit'] / $item['net_sales']) * 100, 1) : 0.0;
            if (abs($margin) < 0.05) {
                $margin = 0.0;
            }
            $item['margin'] = $margin;
            $item['invoice_breakdown'] = array_values($item['invoice_breakdown']);

            return $item;
        })->sortByDesc('net_sales')->values();
    }

    /**
     * Generate streamed CSV file of all sales in the date range.
     */
    public function exportCsv(Carbon $startDate, Carbon $endDate): StreamedResponse
    {
        $filename = sprintf(
            'sales-report-%s-to-%s.csv',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $sales = Sale::query()
            ->with([
                'customer',
                'creator',
                'items.product.company',
                'items.service',
                'items.inventoryMovements.purchaseItem.purchase',
            ])
            ->where('status', SaleStatus::COMPLETED)
            ->whereBetween('sale_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->orderBy('sale_date', 'desc')
            ->get();

        $this->attachCogsAndProfitToSales($sales);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($sales) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel compatibility with UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header
            fputcsv($handle, [
                'Invoice #',
                'Date & Time',
                'Customer Name',
                'Customer Phone',
                'Payment Method',
                'Gross Amount (INR)',
                'Discount (INR)',
                'Cash Received / Total (INR)',
                'Items Net Cost (PO Price) (INR)',
                'Gross Profit (INR)',
                'Margin (%)',
                'Cashier',
                'Status',
            ]);

            foreach ($sales as $sale) {
                $method = $sale->payment_method instanceof PaymentMethod
                    ? $sale->payment_method->label()
                    : ucfirst((string) $sale->payment_method);

                $discount = (float) ($sale->total_discount ?? 0);

                fputcsv($handle, [
                    $sale->invoice_number,
                    $sale->sale_date ? $sale->sale_date->format('Y-m-d H:i') : '',
                    $sale->customer?->name ?? 'Walk-in Customer',
                    $sale->customer?->phone ?? '',
                    $method,
                    number_format((float) $sale->subtotal, 2, '.', ''),
                    number_format($discount, 2, '.', ''),
                    number_format((float) $sale->total, 2, '.', ''),
                    number_format((float) ($sale->cogs ?? 0), 2, '.', ''),
                    number_format((float) ($sale->gross_profit ?? 0), 2, '.', ''),
                    number_format((float) ($sale->margin ?? 0), 1, '.', ''),
                    $sale->creator?->name ?? 'N/A',
                    $sale->status instanceof SaleStatus ? $sale->status->label() : ucfirst((string) $sale->status),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export full or section-specific report data to real Excel XLSX workbook.
     */
    public function exportXls(Carbon $startDate, Carbon $endDate, string $section = 'full'): BinaryFileResponse
    {
        $data = $this->getReportData($startDate, $endDate);

        $writer = new Writer;
        $tempPath = tempnam(sys_get_temp_dir(), 'sales_xls_').'.xlsx';
        $writer->openToFile($tempPath);

        switch ($section) {
            case 'invoices':
                $filename = sprintf('sales-invoices-%s-to-%s.xlsx', $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
                $this->writeInvoicesSheet($writer, $data['sales'], false);
                $this->writeInvoiceItemsSheet($writer, $data['sales'], true);
                break;

            case 'brands':
                $filename = sprintf('sales-brands-and-items-%s-to-%s.xlsx', $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
                $this->writeBrandsSheet($writer, $data['brand_breakdown'], false);
                $this->writeBrandItemsSheet($writer, $data['brand_breakdown'], true);
                break;

            case 'items':
                $filename = sprintf('sales-items-%s-to-%s.xlsx', $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
                $this->writeItemsSheet($writer, $data['item_breakdown'], false);
                $this->writeItemInvoicesSheet($writer, $data['item_breakdown'], true);
                break;

            case 'daily':
                $filename = sprintf('sales-daily-breakdown-%s-to-%s.xlsx', $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
                $this->writeDailySheet($writer, $data['daily_breakdown'], false);
                break;

            case 'full':
            default:
                $filename = sprintf('sales-report-%s-to-%s.xlsx', $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
                $this->writeKpisSheet($writer, $data['kpis'], $data['payment_methods'], $startDate, $endDate);
                $this->writeDailySheet($writer, $data['daily_breakdown'], true);
                $this->writeBrandsSheet($writer, $data['brand_breakdown'], true);
                $this->writeBrandItemsSheet($writer, $data['brand_breakdown'], true);
                $this->writeInvoicesSheet($writer, $data['sales'], true);
                $this->writeInvoiceItemsSheet($writer, $data['sales'], true);
                break;
        }

        $writer->close();

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    protected function writeKpisSheet(Writer $writer, array $kpis, array|Collection $paymentMethods, Carbon $startDate, Carbon $endDate): void
    {
        $writer->getCurrentSheet()->setName('Summary & KPIs');

        $writer->addRow(Row::fromValues(['SALES REPORT SUMMARY & KPIS']));
        $writer->addRow(Row::fromValues([
            'Period:',
            $startDate->format('d M Y').' to '.$endDate->format('d M Y'),
        ]));
        $writer->addRow(Row::fromValues(['Generated At:', now()->format('d M Y, h:i A')]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['METRIC', 'VALUE (INR / COUNT)']));
        $writer->addRow(Row::fromValues(['Total Completed Orders', $kpis['total_orders']]));
        $writer->addRow(Row::fromValues(['Gross Retail Sales (MRP)', (float) $kpis['gross_sales']]));
        $writer->addRow(Row::fromValues(['Total Discounts Given', (float) ($kpis['total_discounts'] ?? 0.0)]));
        $writer->addRow(Row::fromValues(['Net Sales / Cash Received', (float) ($kpis['net_sales'] ?? 0.0)]));
        $writer->addRow(Row::fromValues(['Merchandise PO Net Cost (COGS)', (float) ($kpis['cogs'] ?? $kpis['total_cogs'] ?? 0.0)]));
        $writer->addRow(Row::fromValues(['Gross Profit', (float) ($kpis['gross_profit'] ?? 0.0)]));
        $writer->addRow(Row::fromValues(['Gross Margin (%)', ($kpis['margin'] ?? 0.0).'%']));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['PAYMENT METHOD', 'ORDERS COUNT', 'COLLECTED AMOUNT (INR)', 'SHARE (%)']));
        foreach ($paymentMethods as $pm) {
            $writer->addRow(Row::fromValues([
                $pm['label'] ?? $pm['name'] ?? 'Payment',
                $pm['count'] ?? 0,
                (float) ($pm['amount'] ?? 0.0),
                ($pm['percentage'] ?? 0.0).'%',
            ]));
        }
    }

    protected function writeDailySheet(Writer $writer, Collection $dailyBreakdown, bool $isAdditionalSheet = false): void
    {
        if ($isAdditionalSheet) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('Daily Breakdown');
        } else {
            $writer->getCurrentSheet()->setName('Daily Breakdown');
        }

        $writer->addRow(Row::fromValues([
            'Date',
            'Orders Count',
            'Gross Sales (INR)',
            'Discounts (INR)',
            'Total Sales (INR)',
            'Gross Profit (INR)',
            'Margin (%)',
            'UPI (INR)',
            'Card (INR)',
            'Cash (INR)',
            'Bank Transfer (INR)',
        ]));

        $totalOrders = 0;
        $totalGross = 0.0;
        $totalDisc = 0.0;
        $totalNet = 0.0;
        $totalProfit = 0.0;
        $totalUpi = 0.0;
        $totalCard = 0.0;
        $totalCash = 0.0;
        $totalTransfer = 0.0;

        foreach ($dailyBreakdown as $row) {
            $totalOrders += (int) $row['orders_count'];
            $totalGross += (float) $row['gross_sales'];
            $totalDisc += (float) $row['total_discounts'];
            $totalNet += (float) $row['net_sales'];
            $totalProfit += (float) $row['gross_profit'];
            $totalUpi += (float) $row['upi'];
            $totalCard += (float) $row['card'];
            $totalCash += (float) $row['cash'];
            $totalTransfer += (float) $row['transfer'];

            $writer->addRow(Row::fromValues([
                $row['formatted_date'],
                (int) $row['orders_count'],
                (float) $row['gross_sales'],
                (float) $row['total_discounts'],
                (float) $row['net_sales'],
                (float) $row['gross_profit'],
                $row['margin'].'%',
                (float) $row['upi'],
                (float) $row['card'],
                (float) $row['cash'],
                (float) $row['transfer'],
            ]));
        }

        $overallMargin = $totalNet > 0 ? round(($totalProfit / $totalNet) * 100, 1) : 0.0;
        $writer->addRow(Row::fromValues([
            'TOTAL',
            $totalOrders,
            round($totalGross, 2),
            round($totalDisc, 2),
            round($totalNet, 2),
            round($totalProfit, 2),
            $overallMargin.'%',
            round($totalUpi, 2),
            round($totalCard, 2),
            round($totalCash, 2),
            round($totalTransfer, 2),
        ]));
    }

    protected function writeBrandsSheet(Writer $writer, Collection $brandBreakdown, bool $isAdditionalSheet = false): void
    {
        if ($isAdditionalSheet) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('Brand Summary');
        } else {
            $writer->getCurrentSheet()->setName('Brand Summary');
        }

        $writer->addRow(Row::fromValues([
            'Brand / Category',
            'Unique Items',
            'Units Sold',
            'Gross Retail Sales (INR)',
            'Discounts (INR)',
            'Net Sales (INR)',
            'PO Net Cost (INR)',
            'Gross Profit (INR)',
            'Margin (%)',
            'Revenue Share (%)',
        ]));

        $totQty = 0;
        $totGross = 0.0;
        $totDisc = 0.0;
        $totNet = 0.0;
        $totCost = 0.0;
        $totProfit = 0.0;

        foreach ($brandBreakdown as $b) {
            $bName = $b['name'] ?? $b['brand_name'] ?? 'Unknown';
            $bItemCount = (int) ($b['items_count'] ?? $b['item_count'] ?? count($b['items'] ?? []));
            $bQty = (int) ($b['total_quantity'] ?? 0);
            $bGross = (float) ($b['gross_sales'] ?? $b['total_gross'] ?? 0.0);
            $bDisc = (float) ($b['total_discounts'] ?? 0.0);
            $bNet = (float) ($b['net_sales'] ?? $b['total_net_sales'] ?? 0.0);
            $bCost = (float) ($b['cogs'] ?? $b['total_cogs'] ?? 0.0);
            $bProfit = (float) ($b['gross_profit'] ?? $b['total_gross_profit'] ?? 0.0);
            $bMargin = $b['margin'] ?? 0.0;
            $bShare = $b['percentage'] ?? $b['share_percentage'] ?? 0.0;

            $totQty += $bQty;
            $totGross += $bGross;
            $totDisc += $bDisc;
            $totNet += $bNet;
            $totCost += $bCost;
            $totProfit += $bProfit;

            $writer->addRow(Row::fromValues([
                $bName,
                $bItemCount,
                $bQty,
                $bGross,
                $bDisc,
                $bNet,
                $bCost,
                $bProfit,
                $bMargin.'%',
                $bShare.'%',
            ]));
        }

        $totMargin = $totNet > 0 ? round(($totProfit / $totNet) * 100, 1) : 0.0;
        $writer->addRow(Row::fromValues([
            'TOTAL',
            '-',
            $totQty,
            round($totGross, 2),
            round($totDisc, 2),
            round($totNet, 2),
            round($totCost, 2),
            round($totProfit, 2),
            $totMargin.'%',
            '100.0%',
        ]));
    }

    protected function writeBrandItemsSheet(Writer $writer, Collection $brandBreakdown, bool $isAdditionalSheet = false): void
    {
        if ($isAdditionalSheet) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('Sales by Item by Brand');
        } else {
            $writer->getCurrentSheet()->setName('Sales by Item by Brand');
        }

        $writer->addRow(Row::fromValues([
            'Brand / Category',
            'Item Name',
            'Type',
            'Units Sold',
            'Unit MRP (INR)',
            'Discounts (INR)',
            'Net Sales (INR)',
            'PO Net Unit Price (INR)',
            'Total PO Cost (INR)',
            'Gross Profit (INR)',
            'Margin (%)',
            'Invoices Count',
        ]));

        foreach ($brandBreakdown as $b) {
            $bName = $b['name'] ?? $b['brand_name'] ?? 'Unknown';
            foreach ($b['items'] as $item) {
                $iName = $item['name'] ?? 'Unknown';
                $isServ = ! empty($item['is_service']);
                $iQty = (int) ($item['quantity'] ?? 0);
                $iPrice = (float) ($item['unit_price'] ?? $item['mrp'] ?? 0.0);
                $iDisc = (float) ($item['total_discounts'] ?? 0.0);
                $iNet = (float) ($item['net_sales'] ?? $item['total_net_sales'] ?? 0.0);
                $iPoUnit = (float) ($item['po_unit_price'] ?? 0.0);
                $iCost = (float) ($item['cogs'] ?? $item['total_cogs'] ?? 0.0);
                $iProfit = (float) ($item['gross_profit'] ?? 0.0);
                $iMargin = $item['margin'] ?? 0.0;
                $iInvCount = (int) ($item['invoices_count'] ?? $item['invoice_count'] ?? 0);

                $writer->addRow(Row::fromValues([
                    $bName,
                    $iName,
                    $isServ ? 'Service' : 'Product',
                    $iQty,
                    $iPrice,
                    $iDisc,
                    $iNet,
                    $iPoUnit,
                    $iCost,
                    $iProfit,
                    $iMargin.'%',
                    $iInvCount,
                ]));
            }
        }
    }

    protected function writeInvoicesSheet(Writer $writer, Collection $sales, bool $isAdditionalSheet = false): void
    {
        if ($isAdditionalSheet) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('Completed Invoices');
        } else {
            $writer->getCurrentSheet()->setName('Completed Invoices');
        }

        $writer->addRow(Row::fromValues([
            'Invoice #',
            'Sale Date & Time',
            'Customer Name',
            'Customer Phone',
            'Payment Method',
            'Products Count',
            'Services Count',
            'Gross MRP (INR)',
            'Discounts (INR)',
            'Coupon Code',
            'Coupon Disc (INR)',
            'Bill Disc (INR)',
            'Round Off (INR)',
            'Cash Received / Total (INR)',
            'Items Net Cost (INR)',
            'Gross Profit (INR)',
            'Margin (%)',
            'Cashier',
            'Status',
        ]));

        $totGross = 0.0;
        $totDisc = 0.0;
        $totNet = 0.0;
        $totCost = 0.0;
        $totProfit = 0.0;

        foreach ($sales as $sale) {
            $method = $sale->payment_method instanceof PaymentMethod
                ? $sale->payment_method->label()
                : ucfirst((string) $sale->payment_method);

            $pCount = $sale->items->filter(fn ($i) => ! empty($i->product_id))->count();
            $sCount = $sale->items->filter(fn ($i) => ! empty($i->service_id))->count();

            $gross = (float) $sale->subtotal;
            $disc = (float) ($sale->total_discount ?? 0);
            $net = (float) $sale->total;
            $cost = (float) ($sale->cogs ?? 0);
            $profit = (float) ($sale->gross_profit ?? 0);
            $margin = (float) ($sale->margin ?? 0);

            $saleCouponDiscount = (float) ($sale->coupon_discount ?? 0);
            if ($saleCouponDiscount == 0 && ! empty($sale->coupon_code) && (float) ($sale->global_discount ?? 0) > 0 && (float) ($sale->round_off ?? 0) == 0) {
                $saleCouponDiscount = (float) $sale->global_discount;
            }
            $saleBillDiscount = max(0.0, round(((float) ($sale->global_discount ?? 0)) - $saleCouponDiscount, 2));
            $saleRoundOff = (float) ($sale->round_off ?? 0);
            if ($saleRoundOff == 0 && empty($sale->coupon_code) && (float) ($sale->global_discount ?? 0) != 0) {
                $saleRoundOff = -((float) $sale->global_discount);
            }

            $totGross += $gross;
            $totDisc += $disc;
            $totNet += $net;
            $totCost += $cost;
            $totProfit += $profit;

            $writer->addRow(Row::fromValues([
                $sale->invoice_number,
                $sale->sale_date ? $sale->sale_date->format('Y-m-d H:i') : '',
                $sale->customer?->name ?? 'Walk-in Customer',
                $sale->customer?->phone ?? '',
                $method,
                $pCount,
                $sCount,
                $gross,
                $disc,
                $sale->coupon_code ?: '-',
                $saleCouponDiscount,
                $saleBillDiscount,
                $saleRoundOff,
                $net,
                $cost,
                $profit,
                $margin.'%',
                $sale->creator?->name ?? 'N/A',
                $sale->status instanceof SaleStatus ? $sale->status->label() : ucfirst((string) $sale->status),
            ]));
        }

        $overallMargin = $totNet > 0 ? round(($totProfit / $totNet) * 100, 1) : 0.0;
        $writer->addRow(Row::fromValues([
            'TOTAL',
            '-',
            '-',
            '-',
            '-',
            '-',
            '-',
            round($totGross, 2),
            round($totDisc, 2),
            '-',
            '-',
            '-',
            '-',
            round($totNet, 2),
            round($totCost, 2),
            round($totProfit, 2),
            $overallMargin.'%',
            '-',
            '-',
        ]));
    }

    protected function writeInvoiceItemsSheet(Writer $writer, Collection $sales, bool $isAdditionalSheet = false): void
    {
        if ($isAdditionalSheet) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('Invoice Line Items');
        } else {
            $writer->getCurrentSheet()->setName('Invoice Line Items');
        }

        $writer->addRow(Row::fromValues([
            'Invoice #',
            'Sale Date',
            'Item Name',
            'Type',
            'Qty',
            'Unit MRP (INR)',
            'Total MRP (INR)',
            'Unit Discount (INR)',
            'Total Discount (INR)',
            'Net Received (INR)',
            'PO Net Unit Price (INR)',
            'Total PO Cost (INR)',
            'Line Profit (INR)',
            'Margin (%)',
            'PO / Cost Source',
        ]));

        foreach ($sales as $sale) {
            $dateStr = $sale->sale_date ? $sale->sale_date->format('Y-m-d') : '';
            foreach ($sale->items as $item) {
                $isServ = ! empty($item->service_id);
                $qty = (int) $item->quantity;
                $unitMrp = (float) $item->unit_price;
                $totMrp = $unitMrp * $qty;
                $unitDisc = (float) $item->discount;
                $totDisc = $unitDisc * $qty;
                $netRec = (float) $item->subtotal;
                $poUnit = (float) ($item->po_unit_price ?? 0);
                $poCost = (float) ($item->po_cost_total ?? 0);
                $lineProfit = round($netRec - $poCost, 2);
                $margin = $netRec > 0 ? round(($lineProfit / $netRec) * 100, 1) : 0.0;
                $source = $item->po_source ?? ($isServ ? 'Service (0 COGS)' : 'Catalog price');

                $writer->addRow(Row::fromValues([
                    $sale->invoice_number,
                    $dateStr,
                    $item->item_name,
                    $isServ ? 'Service' : 'Product',
                    $qty,
                    $unitMrp,
                    $totMrp,
                    $unitDisc,
                    $totDisc,
                    $netRec,
                    $poUnit,
                    $poCost,
                    $lineProfit,
                    $margin.'%',
                    $source,
                ]));
            }
        }
    }

    protected function writeItemsSheet(Writer $writer, Collection $itemBreakdown, bool $isAdditionalSheet = false): void
    {
        if ($isAdditionalSheet) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('Sales by Item');
        } else {
            $writer->getCurrentSheet()->setName('Sales by Item');
        }

        $writer->addRow(Row::fromValues([
            'Item Name',
            'Code / SKU',
            'Brand / Category',
            'Type',
            'Units Sold',
            'Unit MRP (INR)',
            'Discounts (INR)',
            'Net Sales (INR)',
            'PO Net Unit Price (INR)',
            'Total PO Cost (INR)',
            'Gross Profit (INR)',
            'Margin (%)',
            'Invoices Count',
            'Related Invoices',
        ]));

        foreach ($itemBreakdown as $item) {
            $writer->addRow(Row::fromValues([
                $item['name'],
                $item['code'],
                $item['brand_name'],
                $item['is_service'] ? 'Service' : 'Product',
                (int) $item['quantity'],
                (float) $item['unit_price'],
                (float) $item['total_discounts'],
                (float) $item['net_sales'],
                (float) $item['po_unit_price'],
                (float) $item['cogs'],
                (float) $item['gross_profit'],
                $item['margin'].'%',
                (int) $item['invoices_count'],
                implode(', ', $item['invoices'] ?? []),
            ]));
        }
    }

    protected function writeItemInvoicesSheet(Writer $writer, Collection $itemBreakdown, bool $isAdditionalSheet = false): void
    {
        if ($isAdditionalSheet) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('Item Invoices Breakdown');
        } else {
            $writer->getCurrentSheet()->setName('Item Invoices Breakdown');
        }

        $writer->addRow(Row::fromValues([
            'Item Name',
            'Code / SKU',
            'Brand',
            'Invoice #',
            'Date',
            'Customer',
            'Phone',
            'Payment Mode',
            'Qty Sold',
            'Unit MRP (INR)',
            'Gross (INR)',
            'Discount (INR)',
            'Net Sales (INR)',
            'PO Net Cost (INR)',
            'Gross Profit (INR)',
            'Margin (%)',
        ]));

        foreach ($itemBreakdown as $item) {
            foreach ($item['invoice_breakdown'] ?? [] as $inv) {
                $writer->addRow(Row::fromValues([
                    $item['name'],
                    $item['code'],
                    $item['brand_name'],
                    $inv['invoice_number'],
                    $inv['sale_date'],
                    $inv['customer_name'],
                    $inv['customer_phone'] ?? '-',
                    $inv['payment_label'],
                    (int) $inv['quantity'],
                    (float) $inv['unit_price'],
                    (float) $inv['gross'],
                    (float) $inv['discount'],
                    (float) $inv['net_received'],
                    (float) $inv['cogs'],
                    (float) $inv['gross_profit'],
                    $inv['margin'].'%',
                ]));
            }
        }
    }
}
