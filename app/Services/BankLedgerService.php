<?php

namespace App\Services;

use App\Enums\BankTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\BankTransaction;
use App\Models\FinanceCategory;
use App\Models\Sale;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class BankLedgerService
{
    /**
     * Synchronize a bank sale to the bank transactions ledger.
     * Completed sales with payment method UPI, CARD, TRANSFER, or credit are recorded as bank inflows.
     * If the sale is cash, pending, or cancelled, any existing bank ledger entry is voided.
     */
    public function recordSaleTransaction(Sale $sale): ?BankTransaction
    {
        $method = $sale->payment_method instanceof PaymentMethod
            ? $sale->payment_method->value
            : (string) $sale->payment_method;

        $isBankMethod = in_array($method, [
            PaymentMethod::UPI->value,
            PaymentMethod::CARD->value,
            PaymentMethod::TRANSFER->value,
            'upi',
            'card',
            'transfer',
            'credit',
        ], true);

        $isCompleted = $sale->status === SaleStatus::COMPLETED || $sale->status === 'completed';

        if (! $isBankMethod || ! $isCompleted) {
            $this->voidSaleTransaction($sale);

            return null;
        }

        $saleDate = $sale->sale_date ? Carbon::parse($sale->sale_date)->toDateString() : now()->toDateString();
        $accountName = Setting::get('bank_account_name', '1200 Bank Account');

        $sale->load('customer');
        $customerName = $sale->customer?->name ?? 'Walk-in Customer';

        $transactionType = match ($method) {
            PaymentMethod::UPI->value, 'upi' => BankTransactionType::UpiSale,
            PaymentMethod::CARD->value, 'card', 'credit' => BankTransactionType::CardSale,
            PaymentMethod::TRANSFER->value, 'transfer' => BankTransactionType::TransferSale,
            default => BankTransactionType::BankSale,
        };

        return BankTransaction::updateOrCreate(
            [
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
            ],
            [
                'transaction_date' => $saleDate,
                'transaction_type' => $transactionType,
                'reference_number' => $sale->invoice_number,
                'name' => $customerName,
                'description' => $transactionType->label().' - Inv: '.$sale->invoice_number,
                'account_name' => $accountName,
                'split_account' => 'Product Sales',
                'amount' => $sale->total,
                'created_by' => $sale->created_by ?? Auth::id() ?? 1,
            ]
        );
    }

    /**
     * Void / remove the bank ledger transaction associated with a sale.
     */
    public function voidSaleTransaction(Sale $sale): void
    {
        BankTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->delete();
    }

    /**
     * Update transaction date for a sale in the bank ledger.
     */
    public function updateSaleDate(Sale $sale, Carbon|string $saleDate): void
    {
        $parsedDate = $saleDate instanceof Carbon ? $saleDate->toDateString() : Carbon::parse($saleDate)->toDateString();

        BankTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->update([
                'transaction_date' => $parsedDate,
            ]);
    }

    /**
     * Record bank fund addition / bank investment inflow.
     */
    public function recordBankInvestment(array $data, ?int $userId = null): BankTransaction
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Bank investment amount must be greater than zero.');
        }

        $transactionDate = ! empty($data['transaction_date'])
            ? Carbon::parse($data['transaction_date'])->toDateString()
            : now()->toDateString();

        $accountName = Setting::get('bank_account_name', '1200 Bank Account');

        return BankTransaction::create([
            'transaction_date' => $transactionDate,
            'transaction_type' => BankTransactionType::BankInvestment,
            'reference_number' => $data['reference_number'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? 'Bank Investment',
            'account_name' => $accountName,
            'split_account' => $data['split_account'] ?? 'Owner Investment / Capital',
            'finance_category_id' => $data['finance_category_id'] ?? null,
            'amount' => $amount,
            'created_by' => $userId ?? Auth::id() ?? 1,
        ]);
    }

    /**
     * Record a cheque expense outflow.
     */
    public function recordChequeExpense(array $data, ?int $userId = null): BankTransaction
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Cheque expense amount must be greater than zero.');
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

        $accountName = Setting::get('bank_account_name', '1200 Bank Account');

        return BankTransaction::create([
            'transaction_date' => $transactionDate,
            'transaction_type' => BankTransactionType::Expense,
            'reference_number' => $data['reference_number'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? 'Cheque Expense',
            'account_name' => $accountName,
            'split_account' => $splitAccount,
            'finance_category_id' => $categoryId,
            'amount' => $amount,
            'created_by' => $userId ?? Auth::id() ?? 1,
        ]);
    }

    /**
     * Record a cheque drawing outflow.
     */
    public function recordChequeDrawing(array $data, ?int $userId = null): BankTransaction
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Cheque drawing amount must be greater than zero.');
        }

        $transactionDate = ! empty($data['transaction_date'])
            ? Carbon::parse($data['transaction_date'])->toDateString()
            : now()->toDateString();

        $accountName = Setting::get('bank_account_name', '1200 Bank Account');

        return BankTransaction::create([
            'transaction_date' => $transactionDate,
            'transaction_type' => BankTransactionType::Drawing,
            'reference_number' => $data['reference_number'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? 'Owner Cheque Drawing',
            'account_name' => $accountName,
            'split_account' => $data['split_account'] ?? 'Owner Drawings',
            'finance_category_id' => $data['finance_category_id'] ?? null,
            'amount' => $amount,
            'created_by' => $userId ?? Auth::id() ?? 1,
        ]);
    }

    /**
     * Alias for recordChequeExpense.
     */
    public function recordExpense(array $data, ?int $userId = null): BankTransaction
    {
        return $this->recordChequeExpense($data, $userId);
    }

    /**
     * Alias for recordChequeDrawing.
     */
    public function recordDrawing(array $data, ?int $userId = null): BankTransaction
    {
        return $this->recordChequeDrawing($data, $userId);
    }

    /**
     * Update manual transaction (investment, cheque expense, cheque drawing).
     * Prevents editing automated sales transactions directly.
     */
    public function updateManualTransaction(BankTransaction $transaction, array $data): BankTransaction
    {
        if ($transaction->reference_type !== null) {
            throw new InvalidArgumentException('Automated bank sale transactions cannot be modified manually.');
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
    public function deleteManualTransaction(BankTransaction $transaction): void
    {
        if ($transaction->reference_type !== null) {
            throw new InvalidArgumentException('Automated bank sale transactions cannot be deleted manually.');
        }

        $transaction->delete();
    }

    /**
     * Retrieve configured beginning balance as of a given start date.
     */
    public function getBeginningBalance(?string $startDate = null): float
    {
        $baseBalance = (float) Setting::get('bank_beginning_balance', '0');
        $baseDate = Setting::get('bank_beginning_date', null);

        if (! $startDate) {
            return $baseBalance;
        }

        $query = BankTransaction::query()->whereDate('transaction_date', '<', $startDate);

        if (! empty($baseDate)) {
            $query->whereDate('transaction_date', '>=', $baseDate);
        }

        $priorInflows = (clone $query)->inflows()->sum('amount');
        $priorOutflows = (clone $query)->outflows()->sum('amount');

        return round($baseBalance + (float) $priorInflows - (float) $priorOutflows, 2);
    }

    /**
     * Set / update the baseline bank beginning balance and optional starting date.
     */
    public function configureBeginningBalance(float $balance, ?string $date = null): void
    {
        Setting::set('bank_beginning_balance', number_format($balance, 2, '.', ''));

        if (! empty($date)) {
            Setting::set('bank_beginning_date', Carbon::parse($date)->toDateString());
        } else {
            Setting::forget('bank_beginning_date');
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

        $query = BankTransaction::query()->with(['category', 'creator']);

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
        $entries = $transactions->map(function (BankTransaction $txn) use (&$runningBalance, &$totalInflows, &$totalOutflows) {
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
     * Backfill existing completed UPI, Card, Transfer, and credit sales into the bank ledger.
     */
    public function syncHistoricalBankSales(): int
    {
        $sales = Sale::query()
            ->where('status', SaleStatus::COMPLETED)
            ->whereIn('payment_method', [
                PaymentMethod::UPI,
                PaymentMethod::CARD,
                PaymentMethod::TRANSFER,
                'upi',
                'card',
                'transfer',
                'credit',
            ])
            ->get();

        $synced = 0;
        foreach ($sales as $sale) {
            $this->recordSaleTransaction($sale);
            $synced++;
        }

        return $synced;
    }
}
