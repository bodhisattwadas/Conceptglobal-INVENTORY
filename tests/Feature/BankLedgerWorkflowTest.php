<?php

namespace Tests\Feature;

use App\DTOs\SaleData;
use App\Enums\BankTransactionType;
use App\Enums\FinanceCategoryType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Livewire\Finance\BankLedgerTable;
use App\Models\BankTransaction;
use App\Models\Customer;
use App\Models\FinanceCategory;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\BankLedgerService;
use App\Services\CashLedgerService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class BankLedgerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected BankLedgerService $bankLedgerService;

    protected CashLedgerService $cashLedgerService;

    protected SaleService $saleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bankLedgerService = app(BankLedgerService::class);
        $this->cashLedgerService = app(CashLedgerService::class);
        $this->saleService = app(SaleService::class);
    }

    // =========================================================================
    // TIER 1: CORE UNIT & FEATURE TESTS (Happy Paths)
    // =========================================================================

    /**
     * 1. UPI, CARD, and TRANSFER completed sales automatically create BankTransaction records.
     */
    public function test_upi_and_card_and_transfer_sales_automatically_create_bank_ledger_inflows(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::create(['name' => 'Bob Banker', 'phone' => '9876500001']);

        // A. UPI Sale
        $upiSale = $this->createPendingSale($user, total: 1200.00, customer: $customer, method: PaymentMethod::UPI);
        $this->saleService->completeSale($upiSale);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $upiSale->id,
            'transaction_type' => BankTransactionType::UpiSale->value,
            'amount' => 1200.00,
            'name' => 'Bob Banker',
            'split_account' => 'Product Sales',
        ]);

        // B. Card Sale
        $cardSale = $this->createPendingSale($user, total: 2450.00, customer: $customer, method: PaymentMethod::CARD);
        $this->saleService->completeSale($cardSale);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $cardSale->id,
            'transaction_type' => BankTransactionType::CardSale->value,
            'amount' => 2450.00,
        ]);

        // C. Transfer Sale
        $transferSale = $this->createPendingSale($user, total: 5000.00, customer: $customer, method: PaymentMethod::TRANSFER);
        $this->saleService->completeSale($transferSale);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $transferSale->id,
            'transaction_type' => BankTransactionType::TransferSale->value,
            'amount' => 5000.00,
        ]);

        $this->assertDatabaseCount('bank_transactions', 3);
    }

    /**
     * 2. Cash sales do NOT post to bank register.
     */
    public function test_cash_sales_do_not_create_bank_ledger_entries(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $cashSale = $this->createPendingSale($user, total: 800.00, method: PaymentMethod::CASH);
        $this->actingAs($user)->patch(route('sales.complete', $cashSale), ['cash_received' => 800.00]);

        $this->assertDatabaseCount('bank_transactions', 0);
        $this->assertDatabaseCount('cash_transactions', 1);
    }

    /**
     * 3. Bank investment inflow adds funds and updates balance.
     */
    public function test_bank_investment_inflow_recording_increases_bank_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->bankLedgerService->configureBeginningBalance(25000.00);

        $txn = $this->bankLedgerService->recordBankInvestment([
            'transaction_date' => now()->toDateString(),
            'amount' => 15000.00,
            'name' => 'Corporate Partner',
            'reference_number' => 'NEFT-8899',
            'description' => 'Working Capital Injection',
            'split_account' => 'Owner Capital',
        ], $user->id);

        $this->assertSame(BankTransactionType::BankInvestment, $txn->transaction_type);
        $this->assertSame(15000.00, $txn->signed_amount);

        $ledger = $this->bankLedgerService->getLedgerEntries();
        $this->assertSame(25000.00, $ledger['beginning_balance']);
        $this->assertSame(15000.00, $ledger['total_inflows']);
        $this->assertSame(0.00, $ledger['total_outflows']);
        $this->assertSame(40000.00, $ledger['ending_balance']);
    }

    /**
     * 4. Cheque expense outflow decreases balance and records cheque number.
     */
    public function test_cheque_expense_recording_decreases_bank_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->bankLedgerService->configureBeginningBalance(30000.00);

        $category = FinanceCategory::create([
            'name' => 'Office Rent',
            'slug' => 'office-rent',
            'type' => FinanceCategoryType::Expense,
        ]);

        $txn = $this->bankLedgerService->recordExpense([
            'transaction_date' => now()->toDateString(),
            'amount' => 7500.00,
            'name' => 'Commercial Landlord Ltd',
            'reference_number' => 'CHQ-100234',
            'finance_category_id' => $category->id,
            'description' => 'Office lease payment via cheque',
        ], $user->id);

        $this->assertSame(BankTransactionType::Expense, $txn->transaction_type);
        $this->assertSame(-7500.00, $txn->signed_amount);
        $this->assertSame('Office Rent', $txn->split_account);

        $ledger = $this->bankLedgerService->getLedgerEntries();
        $this->assertSame(30000.00, $ledger['beginning_balance']);
        $this->assertSame(7500.00, $ledger['total_outflows']);
        $this->assertSame(22500.00, $ledger['ending_balance']);
    }

    /**
     * 5. Cheque drawing decreases balance.
     */
    public function test_cheque_drawing_recording_decreases_bank_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->bankLedgerService->configureBeginningBalance(20000.00);

        $txn = $this->bankLedgerService->recordDrawing([
            'transaction_date' => now()->toDateString(),
            'amount' => 4000.00,
            'name' => 'Director Jane',
            'reference_number' => 'CHQ-550011',
            'description' => 'Quarterly partner drawing cheque',
        ], $user->id);

        $this->assertSame(BankTransactionType::Drawing, $txn->transaction_type);
        $this->assertSame(-4000.00, $txn->signed_amount);

        $ledger = $this->bankLedgerService->getLedgerEntries();
        $this->assertSame(20000.00, $ledger['beginning_balance']);
        $this->assertSame(4000.00, $ledger['total_outflows']);
        $this->assertSame(16000.00, $ledger['ending_balance']);
    }

    /**
     * 6. Chronological running balance calculation across multiple inflows and outflows.
     */
    public function test_bank_running_balance_calculates_chronologically(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->bankLedgerService->configureBeginningBalance(100000.00, '2026-02-01');

        // Row 1: 2026-02-03 Expense: -12,000 => 88,000
        $this->bankLedgerService->recordExpense([
            'transaction_date' => '2026-02-03',
            'amount' => 12000.00,
            'name' => 'Supplier Alpha',
            'reference_number' => 'CHQ-001',
        ], $user->id);

        // Row 2: 2026-02-07 UPI Sale: +5,000 => 93,000
        $sale1 = $this->createPendingSale($user, total: 5000.00, method: PaymentMethod::UPI);
        $sale1->update(['sale_date' => Carbon::parse('2026-02-07')]);
        $this->saleService->completeSale($sale1);

        // Row 3: 2026-02-12 Cheque Drawing: -8,000 => 85,000
        $this->bankLedgerService->recordDrawing([
            'transaction_date' => '2026-02-12',
            'amount' => 8000.00,
            'name' => 'Partner Share',
            'reference_number' => 'CHQ-002',
        ], $user->id);

        // Row 4: 2026-02-18 Bank Investment: +20,000 => 105,000
        $this->bankLedgerService->recordBankInvestment([
            'transaction_date' => '2026-02-18',
            'amount' => 20000.00,
            'name' => 'Series Angel',
            'reference_number' => 'WIRE-999',
        ], $user->id);

        $ledger = $this->bankLedgerService->getLedgerEntries('2026-02-01', '2026-02-28');

        $this->assertSame(100000.00, $ledger['beginning_balance']);
        $this->assertCount(4, $ledger['entries']);
        $this->assertSame([88000.00, 93000.00, 85000.00, 105000.00], $ledger['entries']->pluck('running_balance')->all());
        $this->assertSame(25000.00, $ledger['total_inflows']);
        $this->assertSame(20000.00, $ledger['total_outflows']);
        $this->assertSame(105000.00, $ledger['ending_balance']);
    }

    // =========================================================================
    // TIER 2: BOUNDARY & VALIDATION TESTS
    // =========================================================================

    /**
     * 7. Service level validation prevents zero or negative amounts.
     */
    public function test_validation_prevents_zero_or_negative_amounts_in_bank_service(): void
    {
        try {
            $this->bankLedgerService->recordBankInvestment(['amount' => 0]);
            $this->fail('Expected InvalidArgumentException for investment amount 0');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        try {
            $this->bankLedgerService->recordExpense(['amount' => -500]);
            $this->fail('Expected InvalidArgumentException for expense amount -500');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        try {
            $this->bankLedgerService->recordDrawing(['amount' => 0]);
            $this->fail('Expected InvalidArgumentException for drawing amount 0');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * 8. Livewire component validates positive amounts and inputs.
     */
    public function test_livewire_bank_component_validates_positive_amounts_and_inputs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(BankLedgerTable::class)
            ->set('inv_amount', 0)
            ->call('saveInvestment')
            ->assertHasErrors(['inv_amount' => 'gt'])
            ->set('exp_amount', -250)
            ->call('saveExpense')
            ->assertHasErrors(['exp_amount' => 'gt'])
            ->set('draw_amount', 0)
            ->call('saveDrawing')
            ->assertHasErrors(['draw_amount' => 'gt']);
    }

    /**
     * 9. Exact boundary date queries and preset filters in bank ledger.
     */
    public function test_boundary_date_filtering_and_single_day_window_in_bank_ledger(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $today = now()->toDateString();

        $this->bankLedgerService->recordBankInvestment([
            'transaction_date' => $today,
            'amount' => 12500.00,
            'description' => 'Today Bank Inflow',
            'name' => 'Investor Today',
        ], $admin->id);

        // Same start and end date
        $singleDay = $this->bankLedgerService->getLedgerEntries($today, $today);
        $this->assertCount(1, $singleDay['entries']);
        $this->assertSame(12500.00, $singleDay['total_inflows']);

        // Livewire preset filter 'today'
        Livewire::actingAs($admin)
            ->test(BankLedgerTable::class)
            ->set('dateFilter', 'today')
            ->assertSee('Today Bank Inflow')
            ->assertSee('12,500.00');
    }

    /**
     * 10. Beginning balance date clearing unsets setting correctly.
     */
    public function test_clearing_bank_beginning_balance_date_unsets_setting(): void
    {
        $this->bankLedgerService->configureBeginningBalance(50000.00, '2026-01-01');
        $this->assertSame('50000.00', Setting::get('bank_beginning_balance'));
        $this->assertSame('2026-01-01', Setting::get('bank_beginning_date'));

        $this->bankLedgerService->configureBeginningBalance(50000.00, null);
        $this->assertNull(Setting::get('bank_beginning_date'));

        $this->bankLedgerService->configureBeginningBalance(60000.00, '');
        $this->assertNull(Setting::get('bank_beginning_date'));
    }

    // =========================================================================
    // TIER 3: CROSS-FEATURE & LIFECYCLE HOOKS
    // =========================================================================

    /**
     * 11. Changing sale payment method moves transaction between cash and bank registers.
     */
    public function test_updating_payment_method_between_cash_and_bank_methods_synchronizes_both_ledgers(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // 1. Create completed UPI sale
        $sale = $this->createPendingSale($admin, total: 3200.00, method: PaymentMethod::UPI);
        $this->saleService->completeSale($sale);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale->id,
            'transaction_type' => BankTransactionType::UpiSale->value,
        ]);
        $this->assertDatabaseMissing('cash_transactions', ['reference_id' => $sale->id]);

        // 2. Switch to CASH
        $this->saleService->updatePaymentMethod($sale, PaymentMethod::CASH, cashReceived: 3200.00);

        $this->assertDatabaseMissing('bank_transactions', ['reference_id' => $sale->id]);
        $this->assertDatabaseHas('cash_transactions', ['reference_id' => $sale->id]);

        // 3. Switch to CARD
        $this->saleService->updatePaymentMethod($sale, PaymentMethod::CARD);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale->id,
            'transaction_type' => BankTransactionType::CardSale->value,
        ]);
        $this->assertDatabaseMissing('cash_transactions', ['reference_id' => $sale->id]);

        // 4. Switch to TRANSFER
        $this->saleService->updatePaymentMethod($sale, PaymentMethod::TRANSFER);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale->id,
            'transaction_type' => BankTransactionType::TransferSale->value,
        ]);
        $this->assertDatabaseMissing('cash_transactions', ['reference_id' => $sale->id]);
    }

    /**
     * 12. Cancelling and deleting a bank sale voids the transaction in bank_transactions.
     */
    public function test_cancelling_and_deleting_bank_sale_voids_bank_ledger_entry(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Cancellation
        $sale1 = $this->createPendingSale($admin, total: 1400.00, method: PaymentMethod::UPI);
        $this->saleService->completeSale($sale1);
        $this->assertDatabaseHas('bank_transactions', ['reference_id' => $sale1->id]);

        $this->saleService->cancelSale($sale1, 'Customer cancelled order');
        $this->assertDatabaseMissing('bank_transactions', ['reference_id' => $sale1->id]);

        // Deletion
        $sale2 = $this->createPendingSale($admin, total: 2100.00, method: PaymentMethod::CARD);
        $this->saleService->completeSale($sale2);
        $this->assertDatabaseHas('bank_transactions', ['reference_id' => $sale2->id]);

        $this->saleService->deleteSale($sale2);
        $this->assertDatabaseMissing('bank_transactions', ['reference_id' => $sale2->id]);
    }

    /**
     * 13. Sale date update updates transaction date in bank_transactions.
     */
    public function test_sale_date_update_synchronizes_transaction_date_in_bank_ledger(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $sale = $this->createPendingSale($admin, total: 1800.00, method: PaymentMethod::UPI);
        $this->saleService->completeSale($sale);

        $newDate = Carbon::parse('2026-06-15');
        $this->saleService->updateSaleDate($sale, $newDate);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale->id,
            'transaction_date' => '2026-06-15',
        ]);
    }

    /**
     * 14. Historical sale customer update synchronizes name on bank transaction.
     */
    public function test_updating_historical_bank_sale_customer_updates_bank_transaction_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customerA = Customer::create(['name' => 'Charlie First', 'phone' => '1122334455']);
        $customerB = Customer::create(['name' => 'Dana Second', 'phone' => '5566778899']);

        $sale = $this->createPendingSale($admin, total: 900.00, customer: $customerA, method: PaymentMethod::UPI);
        $this->saleService->completeSale($sale);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale->id,
            'name' => 'Charlie First',
        ]);

        $saleData = new SaleData(
            sale_date: now(),
            payment_method: PaymentMethod::UPI,
            created_by: $admin->id,
            items: [],
            customer_id: $customerB->id,
            status: SaleStatus::COMPLETED,
        );

        $this->saleService->updateHistoricalSale($sale, $saleData);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale->id,
            'name' => 'Dana Second',
        ]);
    }

    /**
     * 15. Customer returns on bank sale updates sale total and reflects adjusted amount in bank ledger.
     */
    public function test_customer_returns_on_bank_sale_adjusts_or_reconciles_bank_ledger(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sale = $this->createPendingSale($admin, total: 2000.00, method: PaymentMethod::CARD);
        $this->saleService->completeSale($sale);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale->id,
            'amount' => 2000.00,
        ]);

        // Simulate return reduction
        $sale->update(['total' => 1500.00]);
        $this->bankLedgerService->recordSaleTransaction($sale);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale->id,
            'amount' => 1500.00,
        ]);
    }

    // =========================================================================
    // TIER 4: E2E WORKFLOW, ACCESS CONTROL & UI TESTS
    // =========================================================================

    /**
     * 16. Access control for bank ledger route.
     */
    public function test_access_control_for_bank_ledger(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->get(route('finance.bank-ledger.index'))->assertRedirect(route('login'));
        $this->actingAs($sales)->get(route('finance.bank-ledger.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('finance.bank-ledger.index'))->assertOk();
        $this->actingAs($admin)->get(route('finance.bank-ledger.index'))->assertOk();
    }

    /**
     * 17. Livewire Bank Ledger Table modal workflows and manual deletion rules.
     */
    public function test_livewire_bank_ledger_table_modal_workflows_and_manual_deletion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Save beginning balance
        Livewire::actingAs($admin)
            ->test(BankLedgerTable::class)
            ->set('setting_balance', 45000.00)
            ->set('setting_date', '2026-01-01')
            ->call('saveBeginningBalance')
            ->assertSee('45,000.00');

        $this->assertSame('45000.00', Setting::get('bank_beginning_balance'));

        // Save investment
        Livewire::actingAs($admin)
            ->test(BankLedgerTable::class)
            ->set('inv_date', '2026-03-01')
            ->set('inv_amount', 18000.00)
            ->set('inv_name', 'Tech Ventures')
            ->set('inv_num', 'DEP-BANK-1')
            ->call('saveInvestment')
            ->assertSee('DEP-BANK-1');

        $manualTxn = BankTransaction::where('reference_number', 'DEP-BANK-1')->first();
        $this->assertNotNull($manualTxn);
        $this->assertNull($manualTxn->reference_type);

        // Delete manual transaction
        Livewire::actingAs($admin)
            ->test(BankLedgerTable::class)
            ->call('deleteTransaction', $manualTxn->id)
            ->assertDontSee('DEP-BANK-1');

        $this->assertDatabaseMissing('bank_transactions', ['id' => $manualTxn->id]);

        // Cannot delete automated sale transaction
        $sale = $this->createPendingSale($admin, total: 500.00, method: PaymentMethod::UPI);
        $this->saleService->completeSale($sale);
        $saleTxn = BankTransaction::where('reference_id', $sale->id)->first();

        Livewire::actingAs($admin)
            ->test(BankLedgerTable::class)
            ->call('deleteTransaction', $saleTxn->id)
            ->assertSee('Automated sales transactions cannot be deleted');

        $this->assertDatabaseHas('bank_transactions', ['id' => $saleTxn->id]);
    }

    /**
     * 18. Bank ledger navigation link renders for admin and manager, hidden for sales.
     */
    public function test_bank_ledger_navigation_links_render_for_authorized_roles(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Bank Register');
        $this->actingAs($manager)->get(route('dashboard'))->assertSee('Bank Register');
        $this->actingAs($sales)->get(route('dashboard'))->assertDontSee('Bank Register');
    }

    /**
     * 19. Backfill command syncs completed historical UPI/Card/Transfer sales into bank ledger.
     */
    public function test_sync_bank_ledger_command_backfills_historical_bank_sales(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Create 2 completed UPI/Card sales directly in database without ledger records
        $sale1 = $this->createPendingSale($admin, total: 1100.00, method: PaymentMethod::UPI);
        $sale1->update(['status' => SaleStatus::COMPLETED]);

        $sale2 = $this->createPendingSale($admin, total: 2200.00, method: PaymentMethod::CARD);
        $sale2->update(['status' => SaleStatus::COMPLETED]);

        $this->assertDatabaseCount('bank_transactions', 0);

        $this->artisan('bank-ledger:sync')
            ->assertSuccessful()
            ->expectsOutputToContain('2');

        $this->assertDatabaseCount('bank_transactions', 2);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale1->id,
            'transaction_type' => BankTransactionType::UpiSale->value,
            'amount' => 1100.00,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_id' => $sale2->id,
            'transaction_type' => BankTransactionType::CardSale->value,
            'amount' => 2200.00,
        ]);
    }

    /**
     * 20. Livewire BankLedgerTable handles malformed startDate strings without ViewException.
     */
    public function test_livewire_bank_ledger_table_handles_malformed_start_date_without_view_exception(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(BankLedgerTable::class)
            ->set('startDate', 'garbage-non-date-value')
            ->assertOk()
            ->assertSee('-');
    }

    private function createPendingSale(User $user, float $total, ?Customer $customer = null, PaymentMethod $method = PaymentMethod::UPI): Sale
    {
        return Sale::create([
            'invoice_number' => 'INV-BANK-'.str_pad((string) (Sale::count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => $customer?->id,
            'created_by' => $user->id,
            'sale_date' => now(),
            'status' => SaleStatus::PENDING,
            'subtotal' => $total,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => $total,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => $method,
        ]);
    }
}
