<?php

namespace App\Livewire\Reports;

use App\Enums\DatePeriod;
use App\Services\SalesReportService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReport extends Component
{
    use WithPagination;

    public string $dateFilter = 'this_month';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public function mount(): void
    {
        $this->syncDatesFromPreset();
    }

    public function updatedDateFilter(): void
    {
        if ($this->dateFilter !== DatePeriod::CUSTOM->value) {
            $this->syncDatesFromPreset();
        }
    }

    public function updatedStartDate(): void
    {
        $this->dateFilter = DatePeriod::CUSTOM->value;
    }

    public function updatedEndDate(): void
    {
        $this->dateFilter = DatePeriod::CUSTOM->value;
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

    public function render(SalesReportService $service)
    {
        [$start, $end] = $this->resolveCarbonDates();

        $data = $service->getReportData($start, $end);

        return view('livewire.reports.sales-report', [
            'kpis' => $data['kpis'],
            'paymentMethods' => $data['payment_methods'],
            'dailyBreakdown' => $data['daily_breakdown'],
            'sales' => $data['sales'],
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }
}
