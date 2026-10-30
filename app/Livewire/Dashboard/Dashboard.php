<?php

namespace App\Livewire\Dashboard;

use App\Enums\DatePeriod;
use App\Models\Product;
use App\Models\Sale;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Dashboard extends Component
{
    use WithPagination;

    public string $dateFilter = DatePeriod::THIS_MONTH->value;

    public ?string $customStartDate = null;

    public ?string $customEndDate = null;

    public array $stats = [];

    public int $lowStockCount = 0;

    public array $recentSales = [];

    public array $topProducts = [];

    public array $topCustomers = [];

    // Charts Data
    public array $salesChart = [];

    public array $cashFlowChart = [];

    public array $expenseChart = [];

    public function mount(DashboardStatsService $service)
    {
        $this->loadStats($service);
    }

    public function updatedDateFilter()
    {
        // If Custom is selected, we wait for dates.
        if ($this->dateFilter !== DatePeriod::CUSTOM->value) {
            $this->loadStats(app(DashboardStatsService::class));
        }
    }

    public function refreshDashboard(): void
    {
        $this->loadStats(app(DashboardStatsService::class));
    }

    public function updateCustomRange($startDate, $endDate)
    {
        $this->customStartDate = $startDate;
        $this->customEndDate = $endDate;

        if ($this->dateFilter === DatePeriod::CUSTOM->value) {
            $this->loadStats(app(DashboardStatsService::class));
        }
    }

    public function loadStats(DashboardStatsService $service)
    {
        [$startDate, $endDate] = $this->getDateRange();

        // Audit and log detailed KPI parameters (each product, invoice, discounts, net price, COGS)
        $service->logKpiBreakdown($startDate, $endDate, $this->dateFilter);

        // 1. Sales Stats
        $salesStats = $service->getSalesStats($startDate, $endDate, $this->dateFilter);

        // 2. Cash Flow Stats
        $cashFlowStats = $service->getCashFlowStats($startDate, $endDate, $this->dateFilter);

        $this->stats = [
            'total_sales' => $salesStats['total_revenue'],
            'sales_count' => $salesStats['count'],
            'gross_profit' => $salesStats['gross_profit'],
            'cogs' => $salesStats['cogs'],
            'margin' => $salesStats['margin'],
            'income' => $cashFlowStats['income'],
            'expense' => $cashFlowStats['expense'],
            'net_cash_flow' => $cashFlowStats['net_cash_flow'],
        ];

        // 3. Lists
        $this->lowStockCount = $service->getLowStockCount();
        $this->topProducts = $service->getTopProducts($startDate, $endDate, 5);
        $this->recentSales = $service->getRecentSales(5);
        $this->topCustomers = $service->getTopCustomers($startDate, $endDate, 5);

        // 4. Prepare Chart Data
        $salesTrend = $service->getSalesTrend($startDate, $endDate);

        $this->salesChart = [
            'labels' => array_keys($salesTrend),
            'data' => array_values($salesTrend),
        ];

        $cashFlowTrend = $service->getCashFlowTrend($startDate, $endDate);

        $this->cashFlowChart = [
            'labels' => array_keys($cashFlowTrend['income']),
            'income' => array_values($cashFlowTrend['income']),
            'expense' => array_values($cashFlowTrend['expense']),
        ];

        $expenseBreakdown = $service->getExpenseBreakdown($startDate, $endDate);
        $this->expenseChart = [
            'labels' => array_column($expenseBreakdown, 'category_name'),
            'series' => array_column($expenseBreakdown, 'total_amount'),
        ];

        $this->dispatch('stats-updated', [
            'sales' => $this->salesChart,
            'cashFlow' => $this->cashFlowChart,
            'expense' => $this->expenseChart,
        ]);
    }

    protected function getDateRange(): array
    {
        $now = Carbon::now();

        return match (DatePeriod::tryFrom($this->dateFilter)) {
            DatePeriod::TODAY => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            DatePeriod::YESTERDAY => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            DatePeriod::THIS_WEEK => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            DatePeriod::THIS_MONTH => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            DatePeriod::LAST_MONTH => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            DatePeriod::ALL_TIME => [
                Carbon::parse(Sale::min('sale_date') ?? now()->subYears(5))->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            DatePeriod::CUSTOM => [
                Carbon::parse($this->customStartDate ?: $now->copy()->startOfMonth())->startOfDay(),
                Carbon::parse($this->customEndDate ?: $now)->endOfDay(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    public function render()
    {
        $lowStockProducts = Product::query()
            ->select(['id', 'sku', 'name', 'quantity', 'min_stock'])
            ->whereColumn('quantity', '<=', 'min_stock')
            ->where('is_active', true)
            ->orderBy('quantity')
            ->orderBy('name')
            ->paginate(5, pageName: 'lowStockPage');

        return view('livewire.dashboard.dashboard', [
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}
