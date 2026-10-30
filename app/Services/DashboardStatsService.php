<?php

namespace App\Services;

use App\Models\FinanceTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
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
                ->selectRaw('COUNT(*) as count, SUM(total) as total_revenue')
                ->first();

            $totalRevenue = (float) ($salesData->total_revenue ?? 0);
            $count = (int) ($salesData->count ?? 0);

            // COGS = Sum of (Sales Item Quantity * Cost Price) for physical products only.
            // Services carry zero merchandise inventory cost.
            // If cost_price was 0 at the time of sale, fall back to product purchase_price.
            $cogs = (float) SaleItem::query()
                ->whereHas('sale', function ($query) use ($startStr, $endStr) {
                    $query->whereBetween('sale_date', [$startStr, $endStr])
                        ->where('status', 'completed');
                })
                ->whereNotNull('product_id')
                ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
                ->sum(DB::raw('sale_items.quantity * COALESCE(NULLIF(sale_items.cost_price, 0), products.purchase_price, 0)'));

            $grossProfit = (float) ($totalRevenue - $cogs);
            $margin = $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 1) : 0.0;

            return [
                'total_revenue' => $totalRevenue,
                'count' => $count,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'margin' => $margin,
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
            $endDateStr = $endDate->format('Y-m-d');

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
            $endDateStr = $endDate->format('Y-m-d');

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
            $endDateStr = $endDate->format('Y-m-d');

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
}
