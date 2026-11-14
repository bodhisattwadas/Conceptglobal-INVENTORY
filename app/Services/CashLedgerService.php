<?php

namespace App\Services;

use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\CashTransaction;
use App\Models\FinanceCategory;
use App\Models\Sale;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CashLedgerService
{
    /**
     * Synchronize a cash sale to the cash transactions ledger.
     * Only completed sales with payment method CASH are recorded as inflows.
     * If the sale is not cash or not completed, any existing ledger entry is voided.
     */
    public function recordCashSale(Sale $sale): ?CashTransaction
    {
        $isCash = $sale->payment_method === PaymentMethod::CASH || $sale->payment_method === 'cash';
        $isCompleted = $sale->status === SaleStatus::COMPLETED || $sale->status === 'completed';

        if (! $isCash || ! $isCompleted) {
            $this->voidSaleTransaction($sale);

            return null;
        }

        $saleDate = $sale->sale_date ? Carbon::parse($sale->sale_date)->toDateString() : now()->toDateString();
        $accountName = Setting::get('cash_account_name', '1100 Cash and Cash Equivalent');

        $sale->load('customer');
        $customerName = $sale->customer?->name ?? 'Walk-in Customer';

        return CashTransaction::updateOrCreate(
            [
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
            ],
            [
                'transaction_date' => $saleDate,
                'transaction_type' => CashTransactionType::CashSale,
                'reference_number' => $sale->invoice_number,
                'name' => $customerName,
                'description' => 'Cash Sale - Inv: '.$sale->invoice_number,
                'account_name' => $accountName,
                'split_account' => 'Product Sales',
                'amount' => $sale->total,
                'created_by' => $sale->created_by ?? Auth::id() ?? 1,
            ]
        );
    }

    /**
     * Void / remove the cash ledger transaction associated with a sale.
     */
    public function voidSaleTransaction(Sale $sale): void
    {
        CashTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->delete();
    }

    /**
     * Update transaction date for a sale in the cash ledger.
     */
    public function updateSaleDate(Sale $sale, Carbon|string $saleDate): void
    {
        $parsedDate = $saleDate instanceof Carbon ? $saleDate->toDateString() : Carbon::parse($saleDate)->toDateString();

        CashTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->update([
                'transaction_date' => $parsedDate,
            ]);
    }

    /**
     * Record cash fund addition / cash investment inflow.
     */
    public function recordCashInvestment(array $data, ?int $userId = null): CashTransaction
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Cash investment amount must be greater than zero.');
        }

        $transactionDate = ! empty($data['transaction_date'])
            ? Carbon::parse($data['transaction_date'])->toDateString()
            : now()->toDateString();

        $accountName = Setting::get('cash_account_name', '1100 Cash and Cash Equivalent');

        return CashTransaction::create([
            'transaction_date' => $transactionDate,
            'transaction_type' => CashTransactionType::CashInvestment,
            'reference_number' => $data['reference_number'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? 'Cash Investment',
            'account_name' => $accountName,
            'split_account' => $data['split_account'] ?? 'Owner Investment / Capital',
            'finance_category_id' => $data['finance_category_id'] ?? null,
            'amount' => $amount,
            'created_by' => $userId ?? Auth::id() ?? 1,
        ]);
    }

    /**
     * Record a cash expense outflow.
     */
    public function recordExpense(array $data, ?int $userId = null): CashTransaction
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Expense amount must be greater than zero.');
        }

        $transactionDate = ! empty($data['transaction_date'])
            ? Carbon::parse($data['transaction_date'])->toDateString()
            : now()->toDateString();

        $splitAccount = $data['split_account'] ?? null;
        $categoryId = $data['finance_category_id'] ?? null;

        if (! $splitAccount && $categoryId) {
            $category = FinanceCategory::find($categoryId);
            $splitAccount = $category?->name;
        }

        if (! $splitAccount) {
            $splitAccount = 'General Expense';
        }

        $accountName = Setting::get('cash_account_name', '1100 Cash and Cash Equivalent');

        return CashTransaction::create([
            'transaction_date' => $transactionDate,
            'transaction_type' => CashTransactionType::Expense,
            'reference_number' => $data['reference_number'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? 'Cash Expense',
            'account_name' => $accountName,
            'split_account' => $splitAccount,
            'finance_category_id' => $categoryId,
            'amount' => $amount,
            'created_by' => $userId ?? Auth::id() ?? 1,
        ]);
    }

    /**
     * Record an owner drawing outflow.
     */
    public function recordDrawing(array $data, ?int $userId = null): CashTransaction
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Drawing amount must be greater than zero.');
        }

        $transactionDate = ! empty($data['transaction_date'])
            ? Carbon::parse($data['transaction_date'])->toDateString()
            : now()->toDateString();

        $accountName = Setting::get('cash_account_name', '1100 Cash and Cash Equivalent');

        return CashTransaction::create([
            'transaction_date' => $transactionDate,
            'transaction_type' => CashTransactionType::Drawing,
            'reference_number' => $data['reference_number'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? 'Owner Drawing',
            'account_name' => $accountName,
            'split_account' => $data['split_account'] ?? 'Owner Drawings',
            'finance_category_id' => $data['finance_category_id'] ?? null,
            'amount' => $amount,
            'created_by' => $userId ?? Auth::id() ?? 1,
        ]);
    }

    /**
     * Update manual transaction (investment, expense, drawing).
     * Prevents editing automated sales transactions directly.
     */
    public function updateManualTransaction(CashTransaction $transaction, array $data): CashTransaction
    {
        if ($transaction->reference_type !== null) {
            throw new InvalidArgumentException('Automated cash sale transactions cannot be modified manually.');
        }

        $amount = (float) ($data['amount'] ?? $transaction->amount);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $updateData = [
            'amount' => $amount,
        ];

        if (isset($data['transaction_date'])) {
            $updateData['transaction_date'] = Carbon::parse($data['transaction_date'])->toDateString();
        }
        if (isset($data['reference_number'])) {
            $updateData['reference_number'] = $data['reference_number'];
        }
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['split_account'])) {
            $updateData['split_account'] = $data['split_account'];
        }
        if (array_key_exists('finance_category_id', $data)) {
            $updateData['finance_category_id'] = $data['finance_category_id'];
        }

        $transaction->update($updateData);

        return $transaction->fresh();
    }

    /**
     * Delete manual transaction.
     */
    public function deleteManualTransaction(CashTransaction $transaction): void
    {
        if ($transaction->reference_type !== null) {
            throw new InvalidArgumentException('Automated cash sale transactions cannot be deleted manually.');
        }

        $transaction->delete();
    }

    /**
     * Retrieve configured beginning balance as of a given start date.
     */
    public function getBeginningBalance(?string $startDate = null): float
    {
        $baseBalance = (float) Setting::get('cash_beginning_balance', Setting::get('opening_balance_amount', '0'));
        $baseDate = Setting::get('cash_beginning_date', Setting::get('opening_balance_date', null));

        if (! $startDate) {
            return $baseBalance;
        }

        $query = CashTransaction::query()->whereDate('transaction_date', '<', $startDate);

        if (! empty($baseDate)) {
            $query->whereDate('transaction_date', '>=', $baseDate);
        }

        $priorInflows = (clone $query)->inflows()->sum('amount');
        $priorOutflows = (clone $query)->outflows()->sum('amount');

        return round($baseBalance + (float) $priorInflows - (float) $priorOutflows, 2);
    }

    /**
     * Set / update the baseline cash beginning balance and optional starting date.
     */
    public function configureBeginningBalance(float $balance, ?string $date = null): void
    {
        Setting::set('cash_beginning_balance', number_format($balance, 2, '.', ''));

        if (! empty($date)) {
            Setting::set('cash_beginning_date', Carbon::parse($date)->toDateString());
        } else {
            Setting::forget('cash_beginning_date');
        }
    }

    /**
     * Retrieve ledger entries with sequential running balances.
     */
    public function getLedgerEntries(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $search = null,
        ?string $type = null
    ): array {
        $beginningBalance = $this->getBeginningBalance($startDate);

        $query = CashTransaction::query()->with(['category', 'creator']);

        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }

        if ($type && $type !== 'all') {
            $query->where('transaction_type', $type);
        }

        if ($search && trim($search) !== '') {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('split_account', 'like', "%{$search}%");
            });
        }

        $transactions = $query->chronological('asc')->get();

        $runningBalance = $beginningBalance;
        $totalInflows = 0.0;
        $totalOutflows = 0.0;

        /** @var Collection $entries */
        $entries = $transactions->map(function (CashTransaction $txn) use (&$runningBalance, &$totalInflows, &$totalOutflows) {
            $amount = (float) $txn->amount;
            if ($txn->transaction_type->isOutflow()) {
                $runningBalance = round($runningBalance - $amount, 2);
                $totalOutflows += $amount;
            } else {
                $runningBalance = round($runningBalance + $amount, 2);
                $totalInflows += $amount;
            }

            $txn->running_balance = $runningBalance;

            return $txn;
        });

        return [
            'beginning_balance' => $beginningBalance,
            'entries' => $entries,
            'total_inflows' => round($totalInflows, 2),
            'total_outflows' => round($totalOutflows, 2),
            'ending_balance' => $runningBalance,
        ];
    }

    /**
     * Backfill existing completed cash sales into the cash ledger.
     */
    public function syncHistoricalCashSales(): int
    {
        $sales = Sale::query()
            ->where('status', SaleStatus::COMPLETED)
            ->where('payment_method', PaymentMethod::CASH)
            ->get();

        $synced = 0;
        foreach ($sales as $sale) {
            $this->recordCashSale($sale);
            $synced++;
        }

        return $synced;
    }
}
