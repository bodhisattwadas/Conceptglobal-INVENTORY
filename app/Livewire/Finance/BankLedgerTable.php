<?php

namespace App\Livewire\Finance;

use App\Models\BankTransaction;
use App\Models\FinanceCategory;
use App\Models\Setting;
use App\Services\BankLedgerService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class BankLedgerTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $dateFilter = 'all_time';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public string $typeFilter = 'all';

    public bool $isCollapsed = false;

    // Investment Modal State
    public bool $showInvestmentModal = false;

    public ?string $inv_date = null;

    public ?string $inv_amount = null;

    public ?string $inv_name = null;

    public ?string $inv_num = null;

    public ?string $inv_description = null;

    public ?string $inv_split = 'Owner Investment / Capital';

    // Expense Modal State (Paid by Cheque)
    public bool $showExpenseModal = false;

    public ?string $exp_date = null;

    public ?string $exp_amount = null;

    public ?string $exp_name = null;

    public ?int $exp_category_id = null;

    public ?string $exp_split = null;

    public ?string $exp_num = null; // Cheque Number

    public ?string $exp_description = null;

    // Drawing Modal State (Paid by Cheque)
    public bool $showDrawingModal = false;

    public ?string $draw_date = null;

    public ?string $draw_amount = null;

    public ?string $draw_name = null;

    public ?string $draw_num = null; // Cheque Number

    public ?string $draw_description = null;

    public ?string $draw_split = 'Owner Drawings';

    // Beginning Balance Modal State
    public bool $showBeginningBalanceModal = false;

    public ?string $setting_balance = null;

    public ?string $setting_date = null;

    // Edit Transaction Modal State
    public bool $showEditModal = false;

    public ?int $edit_transaction_id = null;

    public ?string $edit_transaction_type = null;

    public ?string $edit_date = null;

    public ?string $edit_reference_number = null;

    public ?string $edit_name = null;

    public ?string $edit_split_account = null;

    public ?int $edit_finance_category_id = null;

    public ?string $edit_amount = null;

    public ?string $edit_description = null;

    public function mount(): void
    {
        $this->inv_date = now()->toDateString();
        $this->exp_date = now()->toDateString();
        $this->draw_date = now()->toDateString();
        $this->setting_date = Setting::get('bank_beginning_date', Setting::get('opening_balance_date', now()->startOfYear()->toDateString()));
        $this->setting_balance = (string) (Setting::get('bank_beginning_balance', '0.00'));
    }

    public function updatedDateFilter(string $value): void
    {
        $now = Carbon::now();

        switch ($value) {
            case 'today':
                $this->startDate = $now->copy()->startOfDay()->toDateString();
                $this->endDate = $now->copy()->endOfDay()->toDateString();
                break;
            case 'this_week':
                $this->startDate = $now->copy()->startOfWeek()->toDateString();
                $this->endDate = $now->copy()->endOfWeek()->toDateString();
                break;
            case 'this_month':
                $this->startDate = $now->copy()->startOfMonth()->toDateString();
                $this->endDate = $now->copy()->endOfMonth()->toDateString();
                break;
            case 'this_quarter':
                $this->startDate = $now->copy()->firstOfQuarter()->toDateString();
                $this->endDate = $now->copy()->lastOfQuarter()->toDateString();
                break;
            case 'this_year':
                $this->startDate = $now->copy()->startOfYear()->toDateString();
                $this->endDate = $now->copy()->endOfYear()->toDateString();
                break;
            case 'custom':
                // retain custom inputs
                break;
            case 'all_time':
            default:
                $this->startDate = null;
                $this->endDate = null;
                break;
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function toggleCollapse(): void
    {
        $this->isCollapsed = ! $this->isCollapsed;
    }

    // --- Investment Handlers ---
    public function openInvestmentModal(): void
    {
        $this->resetValidation();
        $this->inv_date = now()->toDateString();
        $this->inv_amount = '';
        $this->inv_name = '';
        $this->inv_num = '';
        $this->inv_description = '';
        $this->inv_split = 'Owner Investment / Capital';
        $this->showInvestmentModal = true;
    }

    public function saveInvestment(BankLedgerService $service): void
    {
        $this->validate([
            'inv_date' => 'required|date',
            'inv_amount' => 'required|numeric|gt:0',
            'inv_name' => 'nullable|string|max:255',
            'inv_num' => 'nullable|string|max:100',
            'inv_description' => 'nullable|string',
            'inv_split' => 'nullable|string|max:150',
        ]);

        $service->recordBankInvestment([
            'transaction_date' => $this->inv_date,
            'amount' => (float) $this->inv_amount,
            'name' => $this->inv_name,
            'reference_number' => $this->inv_num,
            'description' => $this->inv_description ?: 'Bank Investment',
            'split_account' => $this->inv_split ?: 'Owner Investment / Capital',
        ], Auth::id());

        $this->showInvestmentModal = false;
        session()->flash('success', 'Bank investment recorded successfully.');
    }

    // --- Expense Handlers (Paid by Cheque) ---
    public function openExpenseModal(): void
    {
        $this->resetValidation();
        $this->exp_date = now()->toDateString();
        $this->exp_amount = '';
        $this->exp_name = '';
        $this->exp_category_id = null;
        $this->exp_split = '';
        $this->exp_num = '';
        $this->exp_description = '';
        $this->showExpenseModal = true;
    }

    public function saveExpense(BankLedgerService $service): void
    {
        $this->validate([
            'exp_date' => 'required|date',
            'exp_amount' => 'required|numeric|gt:0',
            'exp_name' => 'nullable|string|max:255',
            'exp_category_id' => 'nullable|exists:finance_categories,id',
            'exp_split' => 'nullable|string|max:150',
            'exp_num' => 'nullable|string|max:100', // Cheque number
            'exp_description' => 'nullable|string',
        ]);

        $service->recordExpense([
            'transaction_date' => $this->exp_date,
            'amount' => (float) $this->exp_amount,
            'name' => $this->exp_name,
            'finance_category_id' => $this->exp_category_id,
            'split_account' => $this->exp_split,
            'reference_number' => $this->exp_num,
            'description' => $this->exp_description ?: 'Cheque Expense',
        ], Auth::id());

        $this->showExpenseModal = false;
        session()->flash('success', 'Cheque expense recorded successfully.');
    }

    // --- Drawing Handlers (Paid by Cheque) ---
    public function openDrawingModal(): void
    {
        $this->resetValidation();
        $this->draw_date = now()->toDateString();
        $this->draw_amount = '';
        $this->draw_name = '';
        $this->draw_num = '';
        $this->draw_description = '';
        $this->draw_split = 'Owner Drawings';
        $this->showDrawingModal = true;
    }

    public function saveDrawing(BankLedgerService $service): void
    {
        $this->validate([
            'draw_date' => 'required|date',
            'draw_amount' => 'required|numeric|gt:0',
            'draw_name' => 'nullable|string|max:255',
            'draw_num' => 'nullable|string|max:100', // Cheque number
            'draw_description' => 'nullable|string',
            'draw_split' => 'nullable|string|max:150',
        ]);

        $service->recordDrawing([
            'transaction_date' => $this->draw_date,
            'amount' => (float) $this->draw_amount,
            'name' => $this->draw_name,
            'reference_number' => $this->draw_num,
            'description' => $this->draw_description ?: 'Owner Cheque Drawing',
            'split_account' => $this->draw_split ?: 'Owner Drawings',
        ], Auth::id());

        $this->showDrawingModal = false;
        session()->flash('success', 'Owner cheque drawing recorded successfully.');
    }

    // --- Beginning Balance Handlers ---
    public function openBeginningBalanceModal(BankLedgerService $service): void
    {
        $this->resetValidation();
        $this->setting_balance = (string) Setting::get('bank_beginning_balance', '0.00');
        $this->setting_date = Setting::get('bank_beginning_date', Setting::get('opening_balance_date', now()->startOfYear()->toDateString()));
        $this->showBeginningBalanceModal = true;
    }

    public function saveBeginningBalance(BankLedgerService $service): void
    {
        $this->validate([
            'setting_balance' => 'required|numeric|min:0',
            'setting_date' => 'nullable|date',
        ]);

        $service->configureBeginningBalance((float) $this->setting_balance, $this->setting_date);

        $this->showBeginningBalanceModal = false;
        session()->flash('success', 'Bank beginning balance updated successfully.');
    }

    // --- Delete Transaction Handler ---
    public function deleteTransaction(int $id, BankLedgerService $service): void
    {
        $transaction = BankTransaction::findOrFail($id);

        if ($transaction->reference_type !== null) {
            session()->flash('error', 'Automated sales transactions cannot be deleted from the bank ledger. Manage them via Sales.');

            return;
        }

        $service->deleteManualTransaction($transaction);
        session()->flash('success', 'Transaction removed successfully.');
    }

    // --- Edit Transaction Handlers ---
    public function editTransaction(int $id): void
    {
        $this->resetValidation();

        $transaction = BankTransaction::findOrFail($id);

        if ($transaction->reference_type !== null) {
            session()->flash('error', 'Automated sales transactions cannot be modified directly in the bank ledger.');

            return;
        }

        $this->edit_transaction_id = $transaction->id;
        $this->edit_transaction_type = $transaction->transaction_type->value;
        $this->edit_date = $transaction->transaction_date ? $transaction->transaction_date->toDateString() : now()->toDateString();
        $this->edit_reference_number = $transaction->reference_number;
        $this->edit_name = $transaction->name;
        $this->edit_split_account = $transaction->split_account;
        $this->edit_finance_category_id = $transaction->finance_category_id;
        $this->edit_amount = (string) $transaction->amount;
        $this->edit_description = $transaction->description;

        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetValidation();
        $this->edit_transaction_id = null;
        $this->edit_transaction_type = null;
        $this->edit_date = null;
        $this->edit_reference_number = null;
        $this->edit_name = null;
        $this->edit_split_account = null;
        $this->edit_finance_category_id = null;
        $this->edit_amount = null;
        $this->edit_description = null;
    }

    public function updateTransaction(BankLedgerService $service): void
    {
        if (! $this->edit_transaction_id) {
            return;
        }

        $transaction = BankTransaction::findOrFail($this->edit_transaction_id);

        if ($transaction->reference_type !== null) {
            session()->flash('error', 'Automated sales transactions cannot be modified directly in the bank ledger.');
            $this->closeEditModal();

            return;
        }

        if ($this->edit_finance_category_id === '' || $this->edit_finance_category_id === 0) {
            $this->edit_finance_category_id = null;
        }

        $this->validate([
            'edit_date' => 'required|date',
            'edit_amount' => 'required|numeric|gt:0',
            'edit_name' => 'nullable|string|max:255',
            'edit_reference_number' => 'nullable|string|max:100',
            'edit_description' => 'nullable|string',
            'edit_split_account' => 'nullable|string|max:150',
            'edit_finance_category_id' => 'nullable|exists:finance_categories,id',
        ]);

        $service->updateManualTransaction($transaction, [
            'transaction_date' => $this->edit_date,
            'amount' => (float) $this->edit_amount,
            'name' => $this->edit_name,
            'reference_number' => $this->edit_reference_number,
            'description' => $this->edit_description,
            'split_account' => $this->edit_split_account,
            'finance_category_id' => $this->edit_finance_category_id ? (int) $this->edit_finance_category_id : null,
        ]);

        $this->closeEditModal();
        session()->flash('success', 'Bank transaction updated successfully.');
    }

    public function render(BankLedgerService $service): View
    {
        $ledgerData = $service->getLedgerEntries(
            $this->startDate,
            $this->endDate,
            $this->search,
            $this->typeFilter
        );

        $expenseCategories = FinanceCategory::query()
            ->orderBy('name')
            ->get();

        return view('livewire.finance.bank-ledger-table', [
            'beginningBalance' => $ledgerData['beginning_balance'],
            'entries' => $ledgerData['entries'],
            'totalInflows' => $ledgerData['total_inflows'],
            'totalOutflows' => $ledgerData['total_outflows'],
            'endingBalance' => $ledgerData['ending_balance'],
            'expenseCategories' => $expenseCategories,
            'totalCount' => $ledgerData['entries']->count(),
        ]);
    }
}
