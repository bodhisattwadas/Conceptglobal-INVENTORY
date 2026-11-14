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
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $isSplit = in_array($method, [
            PaymentMethod::SPLIT->value,
            'split',
        ], true);

        $isCompleted = $sale->status === SaleStatus::COMPLETED || $sale->status === 'completed';

        if ($isSplit) {
            if (! $isCompleted || (float) $sale->bank_amount <= 0) {
                $this->voidSaleTransaction($sale);

                return null;
            }
        } elseif (! $isBankMethod || ! $isCompleted) {
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
            PaymentMethod::SPLIT->value, 'split' => BankTransactionType::UpiSale,
            default => BankTransactionType::BankSale,
        };

        $description = $isSplit
            ? 'UPI Portion (Split) - Inv: '.$sale->invoice_number
            : $transactionType->label().' - Inv: '.$sale->invoice_number;

        $amount = $isSplit ? (float) $sale->bank_amount : (float) $sale->total;

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
                'description' => $description,
                'account_name' => $accountName,
                'split_account' => 'Product Sales',
                'amount' => $amount,
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
        $this->ensureSalesSynced();

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
     * Ensure any unsynced completed bank/UPI/card sales are backfilled into the ledger.
     */
    public function ensureSalesSynced(): int
    {
        $existingSaleIds = BankTransaction::query()
            ->where('reference_type', Sale::class)
            ->pluck('reference_id');

        $missingSales = Sale::query()
            ->where('status', SaleStatus::COMPLETED)
            ->where(function ($q) {
                $q->whereIn('payment_method', [
                    PaymentMethod::UPI,
                    PaymentMethod::CARD,
                    PaymentMethod::TRANSFER,
                    'upi',
                    'card',
                    'transfer',
                    'credit',
                ])->orWhere(function ($sq) {
                    $sq->where(function ($pq) {
                        $pq->where('payment_method', PaymentMethod::SPLIT)
                            ->orWhere('payment_method', 'split');
                    })->where('bank_amount', '>', 0);
                });
            })
            ->whereNotIn('id', $existingSaleIds)
            ->get();

        $synced = 0;
        foreach ($missingSales as $sale) {
            $this->recordSaleTransaction($sale);
            $synced++;
        }

        return $synced;
    }

    /**
     * Export bank ledger data to an Excel XLSX workbook.
     */
    public function exportXls(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $search = null,
        ?string $type = null
    ): BinaryFileResponse {
        $ledgerData = $this->getLedgerEntries($startDate, $endDate, $search, $type);
        $accountName = Setting::get('bank_account_name', '1200 Bank Account');

        $writer = new Writer;
        $tempPath = tempnam(sys_get_temp_dir(), 'bank_ledger_').'.xlsx';
        $writer->openToFile($tempPath);

        $periodLabel = 'All Time';
        if ($startDate && $endDate) {
            $periodLabel = Carbon::parse($startDate)->format('d M Y').' to '.Carbon::parse($endDate)->format('d M Y');
        } elseif ($startDate) {
            $periodLabel = 'From '.Carbon::parse($startDate)->format('d M Y');
        } elseif ($endDate) {
            $periodLabel = 'Up to '.Carbon::parse($endDate)->format('d M Y');
        }

        $filename = 'bank-ledger-'.($startDate ?: 'all').'-to-'.($endDate ?: 'time').'.xlsx';

        // Title and Metadata Block
        $writer->addRow(Row::fromValues(['BANK REGISTER - 1200 BANK ACCOUNT']));
        $writer->addRow(Row::fromValues(['Account Name:', $accountName]));
        $writer->addRow(Row::fromValues(['Period:', $periodLabel]));
        $writer->addRow(Row::fromValues(['Generated At:', now()->format('d M Y, h:i A')]));
        $writer->addRow(Row::fromValues([]));

        // KPI Summary Block
        $writer->addRow(Row::fromValues([
            'Beginning Balance',
            'Total Bank Inflows',
            'Total Bank Outflows',
            'Net Ending Balance',
        ]));
        $writer->addRow(Row::fromValues([
            (float) $ledgerData['beginning_balance'],
            (float) $ledgerData['total_inflows'],
            (float) $ledgerData['total_outflows'],
            (float) $ledgerData['ending_balance'],
        ]));
        $writer->addRow(Row::fromValues([]));

        // Table Header
        $writer->addRow(Row::fromValues([
            'Date',
            'Transaction Type',
            'Num / Ref #',
            'Name / Entity',
            'Description / Memo',
            'Account Name',
            'Item Split Account',
            'Inflow (INR)',
            'Outflow (INR)',
            'Running Balance (INR)',
        ]));

        // Beginning Balance Row
        $writer->addRow(Row::fromValues([
            $startDate ? Carbon::parse($startDate)->format('d/m/Y') : '-',
            'Beginning Balance',
            '-',
            'Beginning Balance',
            'Opening baseline bank balance',
            $accountName,
            '-',
            0.00,
            0.00,
            (float) $ledgerData['beginning_balance'],
        ]));

        /** @var BankTransaction $txn */
        foreach ($ledgerData['entries'] as $txn) {
            $inflow = $txn->transaction_type->isInflow() ? (float) $txn->amount : 0.00;
            $outflow = $txn->transaction_type->isOutflow() ? (float) $txn->amount : 0.00;

            $writer->addRow(Row::fromValues([
                $txn->transaction_date ? Carbon::parse($txn->transaction_date)->format('d/m/Y') : '-',
                $txn->transaction_type->label(),
                $txn->reference_number ?: '-',
                $txn->name ?: '-',
                $txn->description ?: '-',
                $txn->account_name,
                $txn->split_account ?: ($txn->category?->name ?: '-'),
                $inflow,
                $outflow,
                (float) $txn->running_balance,
            ]));
        }

        // Summary Totals Row
        $writer->addRow(Row::fromValues([
            'TOTALS',
            '',
            '',
            '',
            '',
            '',
            '',
            (float) $ledgerData['total_inflows'],
            (float) $ledgerData['total_outflows'],
            (float) $ledgerData['ending_balance'],
        ]));

        $writer->close();

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Backfill existing completed UPI, Card, Transfer, and credit sales into the bank ledger.
     */
    public function syncHistoricalBankSales(): int
    {
        $sales = Sale::query()
            ->where('status', SaleStatus::COMPLETED)
            ->where(function ($q) {
                $q->whereIn('payment_method', [
                    PaymentMethod::UPI,
                    PaymentMethod::CARD,
                    PaymentMethod::TRANSFER,
                    'upi',
                    'card',
                    'transfer',
                    'credit',
                ])->orWhere(function ($sq) {
                    $sq->where(function ($pq) {
                        $pq->where('payment_method', PaymentMethod::SPLIT)
                            ->orWhere('payment_method', 'split');
                    })->where('bank_amount', '>', 0);
                });
            })
            ->get();

        $synced = 0;
        foreach ($sales as $sale) {
            $this->recordSaleTransaction($sale);
            $synced++;
        }

        return $synced;
    }
}
