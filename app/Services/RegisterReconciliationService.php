<?php

namespace App\Services;

use App\Enums\ReconciliationStatus;
use App\Enums\RegisterType;
use App\Models\RegisterReconciliation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class RegisterReconciliationService
{
    public function __construct(
        protected CashLedgerService $cashLedgerService,
        protected BankLedgerService $bankLedgerService
    ) {}

    /**
     * Compute the monthly ledger summary (beginning balance, inflows, outflows, ending balance).
     *
     * @return array{beginning_balance: float, total_inflows: float, total_outflows: float, recorded_balance: float}
     */
    public function getMonthlySummary(RegisterType $type, int $year, int $month): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $entries = match ($type) {
            RegisterType::Cash => $this->cashLedgerService->getLedgerEntries($startDate, $endDate),
            RegisterType::Bank => $this->bankLedgerService->getLedgerEntries($startDate, $endDate),
        };

        return [
            'beginning_balance' => (float) ($entries['beginning_balance'] ?? 0.0),
            'total_inflows' => (float) ($entries['total_inflows'] ?? 0.0),
            'total_outflows' => (float) ($entries['total_outflows'] ?? 0.0),
            'recorded_balance' => (float) ($entries['ending_balance'] ?? 0.0),
        ];
    }

    /**
     * Save or update a reconciliation record for a specific register and month.
     */
    public function saveReconciliation(
        RegisterType $type,
        int $year,
        int $month,
        float $actualBalance,
        ?string $notes = null,
        ?int $userId = null,
        ?array $denominations = null
    ): RegisterReconciliation {
        if ($userId === null && auth()->check()) {
            $userId = (int) auth()->id();
        }

        if ($userId === null) {
            $userId = User::query()->value('id') ?? 1;
        }

        $summary = $this->getMonthlySummary($type, $year, $month);
        $roundedActual = round($actualBalance, 2);
        $difference = round($roundedActual - (float) $summary['recorded_balance'], 2);
        $status = abs($difference) < 0.001
            ? ReconciliationStatus::Reconciled
            : ReconciliationStatus::Discrepancy;

        return RegisterReconciliation::updateOrCreate(
            [
                'register_type' => $type,
                'year' => $year,
                'month' => $month,
            ],
            [
                'reconciliation_date' => Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString(),
                'beginning_balance' => $summary['beginning_balance'],
                'total_inflows' => $summary['total_inflows'],
                'total_outflows' => $summary['total_outflows'],
                'recorded_balance' => $summary['recorded_balance'],
                'actual_balance' => $roundedActual,
                'difference' => $difference,
                'status' => $status,
                'notes' => $notes,
                'denominations' => $denominations,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ]
        );
    }

    /**
     * Mark a reconciliation record as reviewed/approved by a manager or admin.
     */
    public function reviewReconciliation(RegisterReconciliation $reconciliation, int $reviewerId): RegisterReconciliation
    {
        $reconciliation->update([
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        return $reconciliation->fresh();
    }

    /**
     * Retrieve reconciliation records for history view.
     */
    public function getRecentReconciliations(?RegisterType $type = null, int $limit = 20): Collection
    {
        $query = RegisterReconciliation::query()
            ->with(['reconciler', 'reviewer'])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('id', 'desc');

        if ($type !== null) {
            $query->where('register_type', $type);
        }

        return $query->limit($limit)->get();
    }
}
