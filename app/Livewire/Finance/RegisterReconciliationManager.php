<?php

namespace App\Livewire\Finance;

use App\Enums\ReconciliationStatus;
use App\Enums\RegisterType;
use App\Models\RegisterReconciliation;
use App\Services\RegisterReconciliationService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RegisterReconciliationManager extends Component
{
    public string $activeTab = 'cash';

    public int $selectedYear;

    public int $selectedMonth;

    public ?float $actualBalance = null;

    public ?string $notes = null;

    public array $denominations = [
        '500' => 0,
        '200' => 0,
        '100' => 0,
        '50' => 0,
        '20' => 0,
        '10' => 0,
        'coins' => 0,
    ];

    public function mount(): void
    {
        $this->selectedYear = (int) now()->year;
        $this->selectedMonth = (int) now()->month;
        $this->loadExistingReconciliation();
    }

    public function updatedActiveTab(): void
    {
        $this->loadExistingReconciliation();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadExistingReconciliation();
    }

    public function updatedSelectedMonth(): void
    {
        if ($this->selectedMonth < 1 || $this->selectedMonth > 12) {
            $this->selectedMonth = (int) now()->month;
        }

        $this->loadExistingReconciliation();
    }

    public function loadExistingReconciliation(): void
    {
        $type = RegisterType::tryFrom($this->activeTab) ?? RegisterType::Cash;

        $existing = RegisterReconciliation::query()
            ->where('register_type', $type)
            ->where('year', $this->selectedYear)
            ->where('month', $this->selectedMonth)
            ->first();

        if ($existing) {
            $this->actualBalance = (float) $existing->actual_balance;
            $this->notes = $existing->notes;
            if (! empty($existing->denominations) && is_array($existing->denominations)) {
                $this->denominations = array_merge($this->denominations, $existing->denominations);
            }
        } else {
            $this->actualBalance = null;
            $this->notes = null;
            $this->denominations = [
                '500' => 0,
                '200' => 0,
                '100' => 0,
                '50' => 0,
                '20' => 0,
                '10' => 0,
                'coins' => 0,
            ];
        }
    }

    public function updatedDenominations(): void
    {
        $total = 0.0;
        foreach ($this->denominations as $denom => $count) {
            if ($denom === 'coins') {
                $total += (float) $count;
            } else {
                $total += ((float) $denom) * ((int) $count);
            }
        }
        if ($total > 0) {
            $this->actualBalance = $total;
        }
    }

    public function saveReconciliation(): void
    {
        $this->validate([
            'selectedYear' => 'required|integer|min:2020|max:2099',
            'selectedMonth' => 'required|integer|min:1|max:12',
            'actualBalance' => 'required|numeric',
            'notes' => 'nullable|string|max:2000',
        ]);

        $type = RegisterType::tryFrom($this->activeTab) ?? RegisterType::Cash;
        $service = app(RegisterReconciliationService::class);

        $hasDenominations = false;
        foreach ($this->denominations as $count) {
            if ((float) $count > 0) {
                $hasDenominations = true;
                break;
            }
        }

        $service->saveReconciliation(
            type: $type,
            year: (int) $this->selectedYear,
            month: (int) $this->selectedMonth,
            actualBalance: (float) $this->actualBalance,
            notes: $this->notes,
            userId: auth()->id(),
            denominations: $hasDenominations ? $this->denominations : null
        );

        session()->flash('success', 'Reconciliation saved successfully.');
    }

    public function review(int $id): void
    {
        $record = RegisterReconciliation::findOrFail($id);
        $service = app(RegisterReconciliationService::class);
        $service->reviewReconciliation($record, (int) auth()->id());

        session()->flash('success', 'Reconciliation approved successfully.');
    }

    public function render(): View
    {
        $type = RegisterType::tryFrom($this->activeTab) ?? RegisterType::Cash;
        $service = app(RegisterReconciliationService::class);

        $summary = $service->getMonthlySummary($type, $this->selectedYear, $this->selectedMonth);
        $recordedBalance = (float) $summary['recorded_balance'];

        $existingRecord = RegisterReconciliation::query()
            ->where('register_type', $type)
            ->where('year', $this->selectedYear)
            ->where('month', $this->selectedMonth)
            ->first();

        $difference = null;
        $status = null;

        if ($this->actualBalance !== null && $this->actualBalance !== '' && is_numeric($this->actualBalance)) {
            $difference = round((float) $this->actualBalance - $recordedBalance, 2);
            $status = abs($difference) < 0.001
                ? ReconciliationStatus::Reconciled
                : ReconciliationStatus::Discrepancy;
        } elseif ($existingRecord) {
            $difference = (float) $existingRecord->difference;
            $status = $existingRecord->status;
        }

        $history = $service->getRecentReconciliations(null, 25);

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = Carbon::createFromDate(2026, $m, 1)->format('F');
        }

        $years = range((int) now()->year - 2, (int) now()->year + 1);

        return view('livewire.finance.register-reconciliation-manager', [
            'type' => $type,
            'summary' => $summary,
            'recordedBalance' => $recordedBalance,
            'difference' => $difference,
            'status' => $status,
            'existingRecord' => $existingRecord,
            'history' => $history,
            'months' => $months,
            'years' => $years,
        ]);
    }
}
