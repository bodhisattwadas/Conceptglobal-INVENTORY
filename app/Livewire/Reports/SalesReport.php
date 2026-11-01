<?php

namespace App\Livewire\Reports;

use App\Enums\DatePeriod;
use App\Enums\PaymentMethod;
use App\Services\SalesReportService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReport extends Component
{
    use WithPagination;

    public string $dateFilter = 'this_month';

    public ?string $startDate = null;

    public ?string $endDate = null;

    // Completed Invoices Container Server-Side State
    public string $invoiceSearch = '';

    public string $invoicePaymentMethod = 'all';

    public string $invoiceSortField = 'sale_date';

    public string $invoiceSortDirection = 'desc';

    public int|string $invoicePerPage = 10;

    // Sales by Brand & Items Container Server-Side State
    public string $brandSearch = '';

    public string $brandSortField = 'net_sales';

    public string $brandSortDirection = 'desc';

    public int|string $brandPerPage = 10;

    // Daily Breakdown Container Server-Side State
    public string $dailySearch = '';

    public string $dailySortField = 'date';

    public string $dailySortDirection = 'desc';

    public int|string $dailyPerPage = 10;

    public function mount(): void
    {
        $this->syncDatesFromPreset();
    }

    public function updatedDateFilter(): void
    {
        if ($this->dateFilter !== DatePeriod::CUSTOM->value) {
            $this->syncDatesFromPreset();
            $this->resetAllPages();
        }
    }

    public function updatedStartDate(): void
    {
        $this->dateFilter = DatePeriod::CUSTOM->value;
        $this->resetAllPages();
    }

    public function updatedEndDate(): void
    {
        $this->dateFilter = DatePeriod::CUSTOM->value;
        $this->resetAllPages();
    }

    public function updatedInvoiceSearch(): void
    {
        $this->resetPage('invoicesPage');
    }

    public function updatedInvoicePaymentMethod(): void
    {
        $this->resetPage('invoicesPage');
    }

    public function updatedInvoicePerPage(): void
    {
        $this->resetPage('invoicesPage');
    }

    public function updatedBrandSearch(): void
    {
        $this->resetPage('brandsPage');
    }

    public function updatedBrandPerPage(): void
    {
        $this->resetPage('brandsPage');
    }

    public function updatedDailySearch(): void
    {
        $this->resetPage('dailyPage');
    }

    public function updatedDailyPerPage(): void
    {
        $this->resetPage('dailyPage');
    }

    protected function resetAllPages(): void
    {
        $this->resetPage('invoicesPage');
        $this->resetPage('brandsPage');
        $this->resetPage('dailyPage');
    }

    public function sortByInvoice(string $field): void
    {
        if ($this->invoiceSortField === $field) {
            $this->invoiceSortDirection = $this->invoiceSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->invoiceSortField = $field;
            $this->invoiceSortDirection = in_array($field, ['invoice_number', 'customer']) ? 'asc' : 'desc';
        }
        $this->resetPage('invoicesPage');
    }

    public function sortByBrand(string $field): void
    {
        if ($this->brandSortField === $field) {
            $this->brandSortDirection = $this->brandSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->brandSortField = $field;
            $this->brandSortDirection = in_array($field, ['brand_name']) ? 'asc' : 'desc';
        }
        $this->resetPage('brandsPage');
    }

    public function sortByDaily(string $field): void
    {
        if ($this->dailySortField === $field) {
            $this->dailySortDirection = $this->dailySortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->dailySortField = $field;
            $this->dailySortDirection = 'desc';
        }
        $this->resetPage('dailyPage');
    }

    protected function syncDatesFromPreset(): void
    {
        $now = Carbon::now();

        [$start, $end] = match ($this->dateFilter) {
            DatePeriod::TODAY->value => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            DatePeriod::YESTERDAY->value => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            DatePeriod::THIS_WEEK->value => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            DatePeriod::THIS_MONTH->value => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            DatePeriod::LAST_MONTH->value => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            DatePeriod::ALL_TIME->value => [Carbon::parse('2020-01-01'), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        $this->startDate = $start->format('Y-m-d');
        $this->endDate = $end->format('Y-m-d');
    }

    protected function resolveCarbonDates(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::now()->endOfDay();

        if ($start->greaterThan($end)) {
            $temp = $start;
            $start = $end->copy()->startOfDay();
            $end = $temp->copy()->endOfDay();
        }

        return [$start, $end];
    }

    public function exportCsv(SalesReportService $service): StreamedResponse
    {
        [$start, $end] = $this->resolveCarbonDates();

        return $service->exportCsv($start, $end);
    }

    protected function paginateCollection(Collection $items, int|string $perPageSetting, string $pageName): LengthAwarePaginator
    {
        $total = $items->count();
        $perPage = ($perPageSetting === 'all' || (int) $perPageSetting <= 0)
            ? max(1, $total)
            : (int) $perPageSetting;

        $currentPage = (int) $this->getPage($pageName);
        $currentPage = max(1, $currentPage);

        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($currentPage > $lastPage) {
            $currentPage = $lastPage;
            $this->setPage($currentPage, $pageName);
        }

        $slice = $items->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );
    }

    public function render(SalesReportService $service)
    {
        [$start, $end] = $this->resolveCarbonDates();

        $data = $service->getReportData($start, $end);

        // Filter and Sort Invoices
        $filteredSales = $data['sales']->filter(function ($sale) {
            if ($this->invoicePaymentMethod !== 'all') {
                $methodVal = $sale->payment_method instanceof PaymentMethod
                    ? $sale->payment_method->value
                    : (string) $sale->payment_method;
                if (strtolower($methodVal) !== strtolower($this->invoicePaymentMethod)) {
                    return false;
                }
            }

            if (trim($this->invoiceSearch) !== '') {
                $q = strtolower(trim($this->invoiceSearch));
                $matchInvoice = str_contains(strtolower($sale->invoice_number ?? ''), $q);
                $matchCust = str_contains(strtolower($sale->customer?->name ?? ''), $q);
                $matchPhone = str_contains(strtolower($sale->customer?->phone ?? ''), $q);
                $matchItems = $sale->items->contains(function ($item) use ($q) {
                    return str_contains(strtolower($item->item_name ?? ''), $q);
                });

                if (! $matchInvoice && ! $matchCust && ! $matchPhone && ! $matchItems) {
                    return false;
                }
            }

            return true;
        });

        $sortedSales = $filteredSales->sortBy(function ($sale) {
            return match ($this->invoiceSortField) {
                'invoice_number' => $sale->invoice_number,
                'customer' => strtolower($sale->customer?->name ?? 'Walk-in Customer'),
                'subtotal' => (float) $sale->subtotal,
                'discount' => (float) ($sale->total_discount ?? 0),
                'total' => (float) $sale->total,
                'cogs' => (float) ($sale->cogs ?? 0),
                'gross_profit' => (float) ($sale->gross_profit ?? 0),
                'margin' => (float) ($sale->margin ?? 0),
                'payment_method' => (string) ($sale->payment_method?->value ?? $sale->payment_method),
                default => $sale->sale_date ? $sale->sale_date->timestamp : 0,
            };
        }, descending: $this->invoiceSortDirection === 'desc');

        $paginatedSales = $this->paginateCollection($sortedSales, $this->invoicePerPage, 'invoicesPage');

        // Filter and Sort Brands
        $filteredBrands = $data['brand_breakdown']->filter(function ($b) {
            if (trim($this->brandSearch) === '') {
                return true;
            }
            $q = strtolower(trim($this->brandSearch));
            if (str_contains(strtolower($b['brand_name'] ?? ''), $q)) {
                return true;
            }
            foreach ($b['items'] as $item) {
                if (str_contains(strtolower($item['name'] ?? ''), $q)) {
                    return true;
                }
            }

            return false;
        });

        $sortedBrands = $filteredBrands->sortBy(function ($b) {
            return match ($this->brandSortField) {
                'brand_name' => strtolower($b['name'] ?? $b['brand_name'] ?? ''),
                'quantity' => (int) ($b['total_quantity'] ?? 0),
                'gross_sales' => (float) ($b['gross_sales'] ?? 0),
                'total_discounts' => (float) ($b['total_discounts'] ?? 0),
                'cogs' => (float) ($b['cogs'] ?? 0),
                'gross_profit' => (float) ($b['gross_profit'] ?? 0),
                'margin' => (float) ($b['margin'] ?? 0),
                'percentage' => (float) ($b['percentage'] ?? 0),
                default => (float) ($b['net_sales'] ?? 0),
            };
        }, descending: $this->brandSortDirection === 'desc');

        $paginatedBrands = $this->paginateCollection($sortedBrands, $this->brandPerPage, 'brandsPage');

        // Filter and Sort Daily Breakdown
        $filteredDaily = $data['daily_breakdown']->filter(function ($row) {
            if (trim($this->dailySearch) === '') {
                return true;
            }
            $q = strtolower(trim($this->dailySearch));

            return str_contains(strtolower($row['date'] ?? ''), $q)
                || str_contains(strtolower($row['formatted_date'] ?? ''), $q);
        });

        $sortedDaily = $filteredDaily->sortBy(function ($row) {
            return match ($this->dailySortField) {
                'orders_count' => (int) $row['orders_count'],
                'gross_sales' => (float) $row['gross_sales'],
                'total_discounts' => (float) $row['total_discounts'],
                'net_sales' => (float) $row['net_sales'],
                'gross_profit' => (float) $row['gross_profit'],
                'upi' => (float) $row['upi'],
                'card' => (float) $row['card'],
                'cash' => (float) $row['cash'],
                'transfer' => (float) $row['transfer'],
                default => $row['date'],
            };
        }, descending: $this->dailySortDirection === 'desc');

        $paginatedDaily = $this->paginateCollection($sortedDaily, $this->dailyPerPage, 'dailyPage');

        return view('livewire.reports.sales-report', [
            'kpis' => $data['kpis'],
            'paymentMethods' => $data['payment_methods'],
            'dailyBreakdown' => $paginatedDaily,
            'allDaily' => $data['daily_breakdown'],
            'brandBreakdown' => $paginatedBrands,
            'allBrands' => $data['brand_breakdown'],
            'sales' => $paginatedSales,
            'allSales' => $data['sales'],
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }
}
