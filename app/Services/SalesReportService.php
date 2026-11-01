<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
                'items.inventoryMovements.purchaseItem',
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

        return [
            'kpis' => $kpis,
            'payment_methods' => $paymentMethods,
            'daily_breakdown' => $dailyBreakdown,
            'brand_breakdown' => $brandBreakdown,
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
                } else {
                    $brandKey = 'brand_services';
                    $brandName = 'Salon Services';
                    $isService = true;
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
                $itemKey = $item->isProduct() ? 'prod_'.$item->product_id : 'serv_'.$item->service_id;
                $itemName = $item->item_name;
                $itemCode = $item->isProduct()
                    ? ($item->product?->product_code ?? $item->product?->sku ?? '-')
                    : ($item->service?->code ?? '-');

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
                'items.inventoryMovements.purchaseItem',
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
}
