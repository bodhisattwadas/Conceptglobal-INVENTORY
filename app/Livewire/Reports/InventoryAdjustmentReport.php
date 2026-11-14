<?php

namespace App\Livewire\Reports;

use App\Enums\DatePeriod;
use App\Services\InventoryAdjustmentReportService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryAdjustmentReport extends Component
{
    use WithPagination;

    public string $dateFilter = 'this_month';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public string $typeFilter = 'all'; // 'all' (Damage + Surplus), 'quantity_decrease', 'quantity_increase', 'all_types'

    public string $locationFilter = 'all'; // 'all', 'shop', 'parlour'

    public string $reasonFilter = 'all';

    public string $search = '';

    public int|string $perPage = 15;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    protected $queryString = [
        'dateFilter' => ['except' => 'this_month'],
        'startDate' => ['except' => null],
        'endDate' => ['except' => null],
        'typeFilter' => ['except' => 'all'],
        'locationFilter' => ['except' => 'all'],
        'reasonFilter' => ['except' => 'all'],
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->syncDatesFromPreset();
    }

    public function updatedDateFilter(): void
    {
        if ($this->dateFilter !== DatePeriod::CUSTOM->value) {
            $this->syncDatesFromPreset();
            $this->resetPage();
        }
    }

    public function updatedStartDate(): void
    {
        $this->dateFilter = DatePeriod::CUSTOM->value;
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->dateFilter = DatePeriod::CUSTOM->value;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatingReasonFilter(): void
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
            $this->sortDirection = 'desc';
        }
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->dateFilter = 'this_month';
        $this->syncDatesFromPreset();
        $this->typeFilter = 'all';
        $this->locationFilter = 'all';
        $this->reasonFilter = 'all';
        $this->search = '';
        $this->perPage = 15;
        $this->sortField = 'created_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    protected function syncDatesFromPreset(): void
    {
        $now = Carbon::now();

        switch ($this->dateFilter) {
            case DatePeriod::TODAY->value:
                $this->startDate = $now->copy()->startOfDay()->toDateString();
                $this->endDate = $now->copy()->endOfDay()->toDateString();
                break;

            case DatePeriod::YESTERDAY->value:
                $this->startDate = $now->copy()->subDay()->startOfDay()->toDateString();
                $this->endDate = $now->copy()->subDay()->endOfDay()->toDateString();
                break;

            case DatePeriod::THIS_WEEK->value:
                $this->startDate = $now->copy()->startOfWeek()->toDateString();
                $this->endDate = $now->copy()->endOfWeek()->toDateString();
                break;

            case DatePeriod::THIS_MONTH->value:
                $this->startDate = $now->copy()->startOfMonth()->toDateString();
                $this->endDate = $now->copy()->endOfMonth()->toDateString();
                break;

            case DatePeriod::LAST_MONTH->value:
                $this->startDate = $now->copy()->subMonth()->startOfMonth()->toDateString();
                $this->endDate = $now->copy()->subMonth()->endOfMonth()->toDateString();
                break;

            case DatePeriod::ALL_TIME->value:
                $this->startDate = Carbon::create(2020, 1, 1)->toDateString();
                $this->endDate = $now->copy()->endOfDay()->toDateString();
                break;

            case DatePeriod::CUSTOM->value:
                if (! $this->startDate) {
                    $this->startDate = $now->copy()->startOfMonth()->toDateString();
                }
                if (! $this->endDate) {
                    $this->endDate = $now->copy()->endOfDay()->toDateString();
                }
                break;
        }
    }

    public function render(InventoryAdjustmentReportService $service)
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::now()->endOfDay();

        if ($start->greaterThan($end)) {
            $temp = $start;
            $start = $end->copy()->startOfDay();
            $end = $temp->copy()->endOfDay();
        }

        $filters = [
            'type' => $this->typeFilter,
            'location' => $this->locationFilter,
            'reason' => $this->reasonFilter,
            'search' => $this->search,
            'sort_field' => $this->sortField,
            'sort_direction' => $this->sortDirection,
        ];

        $kpis = $service->getKpiSummary($start, $end, $filters);
        $adjustments = $service->getAdjustmentsQuery($start, $end, $filters)->paginate((int) $this->perPage);

        return view('livewire.reports.inventory-adjustment-report', [
            'kpis' => $kpis,
            'adjustments' => $adjustments,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }
}
