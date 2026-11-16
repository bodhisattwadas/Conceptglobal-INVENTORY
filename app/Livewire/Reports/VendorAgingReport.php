<?php

namespace App\Livewire\Reports;

use App\Models\Supplier;
use App\Services\VendorAgingReportService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class VendorAgingReport extends Component
{
    use WithPagination;

    public string $supplierId = 'all';

    public string $bucket = 'all';

    public string $search = '';

    public string $sortField = 'age_in_days';

    public string $sortDirection = 'desc';

    public int|string $perPage = 15;

    protected $queryString = [
        'supplierId' => ['except' => 'all'],
        'bucket' => ['except' => 'all'],
        'search' => ['except' => ''],
        'sortField' => ['except' => 'age_in_days'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierId(): void
    {
        $this->resetPage();
    }

    public function updatingBucket(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($field, ['age_in_days', 'amount', 'due_amount']) ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->supplierId = 'all';
        $this->bucket = 'all';
        $this->search = '';
        $this->sortField = 'age_in_days';
        $this->sortDirection = 'desc';
        $this->perPage = 15;
        $this->resetPage();
    }

    protected function paginateCollection(Collection $items, int|string $perPageSetting, string $pageName = 'page'): LengthAwarePaginator
    {
        $total = $items->count();
        $perPage = ($perPageSetting === 'all' || (int) $perPageSetting <= 0)
            ? max(1, $total)
            : (int) $perPageSetting;

        $currentPage = max(1, (int) $this->getPage($pageName));
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

    public function render(VendorAgingReportService $service)
    {
        $filters = [
            'supplier_id' => $this->supplierId,
            'bucket' => $this->bucket,
            'search' => $this->search,
            'sort_field' => $this->sortField,
            'sort_direction' => $this->sortDirection,
        ];

        $reportData = $service->getReportData($filters);
        $paginatedInvoices = $this->paginateCollection($reportData['invoices'], $this->perPage, 'page');
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        return view('livewire.reports.vendor-aging-report', [
            'kpis' => $reportData['kpis'],
            'vendorBreakdown' => $reportData['vendor_breakdown'],
            'invoices' => $paginatedInvoices,
            'suppliers' => $suppliers,
            'totalCount' => $reportData['total_invoices_count'],
        ]);
    }
}
