<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ReconciliationStatus;
use App\Enums\RegisterType;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Livewire\Finance\RegisterReconciliationManager;
use App\Models\Sale;
use App\Models\User;
use App\Services\BankLedgerService;
use App\Services\CashLedgerService;
use App\Services\RegisterReconciliationService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthEndReconciliationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected RegisterReconciliationService $reconciliationService;

    protected CashLedgerService $cashLedgerService;

    protected BankLedgerService $bankLedgerService;

    protected SaleService $saleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconciliationService = app(RegisterReconciliationService::class);
        $this->cashLedgerService = app(CashLedgerService::class);
        $this->bankLedgerService = app(BankLedgerService::class);
        $this->saleService = app(SaleService::class);
    }

    // =========================================================================
    // TIER 1: CORE UNIT & FEATURE TESTS (Happy Paths)
    // =========================================================================

    /**
     * 1. Query recorded balance for Cash Register for a given month.
     */
    public function test_calculates_cash_recorded_balance_for_given_month(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(10000.00, '2026-01-01');

        // Jan 10 Inflow: +5000
        $this->cashLedgerService->recordCashInvestment([
            'transaction_date' => '2026-01-10',
            'amount' => 5000.00,
        ], $user->id);

        // Jan 20 Outflow: -2000 => Ending Balance for Jan is 13,000.00
        $this->cashLedgerService->recordExpense([
            'transaction_date' => '2026-01-20',
            'amount' => 2000.00,
        ], $user->id);

        $summary = $this->reconciliationService->getMonthlySummary(RegisterType::Cash, 2026, 1);

        $this->assertSame(13000.00, (float) $summary['recorded_balance']);
        $this->assertSame(10000.00, (float) $summary['beginning_balance']);
        $this->assertSame(5000.00, (float) $summary['total_inflows']);
        $this->assertSame(2000.00, (float) $summary['total_outflows']);
    }

    /**
     * 2. Query recorded balance for Bank Register for a given month.
     */
    public function test_calculates_bank_recorded_balance_for_given_month(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->bankLedgerService->configureBeginningBalance(50000.00, '2026-02-01');

        // Feb 15 Investment: +25000
        $this->bankLedgerService->recordBankInvestment([
            'transaction_date' => '2026-02-15',
            'amount' => 25000.00,
        ], $user->id);

        // Feb 25 Outflow: -5000 => Ending Balance for Feb is 70,000.00
        $this->bankLedgerService->recordExpense([
            'transaction_date' => '2026-02-25',
            'amount' => 5000.00,
            'reference_number' => 'CHQ-111',
        ], $user->id);

        $summary = $this->reconciliationService->getMonthlySummary(RegisterType::Bank, 2026, 2);

        $this->assertSame(70000.00, (float) $summary['recorded_balance']);
    }

    /**
     * 3. Reconciled status when actual balance equals recorded balance (diff = 0).
     */
    public function test_reconciliation_computes_zero_difference_as_reconciled(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(15000.00, '2026-03-01');

        // Physical count matches recorded balance of 15,000.00
        $reconciliation = $this->reconciliationService->saveReconciliation(
            type: RegisterType::Cash,
            year: 2026,
            month: 3,
            actualBalance: 15000.00,
            notes: 'Physical cash count matched perfectly.',
            userId: $user->id
        );

        $this->assertSame(0.00, (float) $reconciliation->difference);
        $this->assertSame(ReconciliationStatus::Reconciled, $reconciliation->status);
        $this->assertTrue($reconciliation->isReconciled());
    }

    /**
     * 4. Discrepancy status when actual balance does not equal recorded balance (diff != 0).
     */
    public function test_reconciliation_computes_nonzero_difference_as_discrepancy(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(20000.00, '2026-04-01');

        // Physical cash count is 19,850.00 (Shortage of 150.00)
        $reconciliation = $this->reconciliationService->saveReconciliation(
            type: RegisterType::Cash,
            year: 2026,
            month: 4,
            actualBalance: 19850.00,
            notes: 'Shortage of Rs. 150 identified during cash drawer count.',
            userId: $user->id
        );

        $this->assertSame(-150.00, (float) $reconciliation->difference);
        $this->assertSame(ReconciliationStatus::Discrepancy, $reconciliation->status);
        $this->assertFalse($reconciliation->isReconciled());
    }

    /**
     * 5. Save reconciliation persists all fields for Cash Register.
     */
    public function test_save_reconciliation_persists_record_for_cash_register(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(10000.00, '2026-05-01');

        $rec = $this->reconciliationService->saveReconciliation(
            type: RegisterType::Cash,
            year: 2026,
            month: 5,
            actualBalance: 10000.00,
            notes: 'May physical count',
            userId: $user->id
        );

        $this->assertDatabaseHas('register_reconciliations', [
            'id' => $rec->id,
            'register_type' => RegisterType::Cash->value,
            'year' => 2026,
            'month' => 5,
            'recorded_balance' => 10000.00,
            'actual_balance' => 10000.00,
            'difference' => 0.00,
            'status' => ReconciliationStatus::Reconciled->value,
            'reconciled_by' => $user->id,
        ]);
    }

    /**
     * 6. Save reconciliation persists all fields for Bank Register.
     */
    public function test_save_reconciliation_persists_record_for_bank_register(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->bankLedgerService->configureBeginningBalance(40000.00, '2026-05-01');

        $rec = $this->reconciliationService->saveReconciliation(
            type: RegisterType::Bank,
            year: 2026,
            month: 5,
            actualBalance: 40500.00,
            notes: 'Bank interest credited on statement',
            userId: $user->id
        );

        $this->assertDatabaseHas('register_reconciliations', [
            'id' => $rec->id,
            'register_type' => RegisterType::Bank->value,
            'year' => 2026,
            'month' => 5,
            'recorded_balance' => 40000.00,
            'actual_balance' => 40500.00,
            'difference' => 500.00,
            'status' => ReconciliationStatus::Discrepancy->value,
        ]);
    }

    /**
     * 7. Manager or Admin can review and sign off on a reconciliation record.
     */
    public function test_manager_or_admin_can_review_and_approve_reconciliation(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Sales]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->cashLedgerService->configureBeginningBalance(10000.00, '2026-06-01');

        $rec = $this->reconciliationService->saveReconciliation(
            type: RegisterType::Cash,
            year: 2026,
            month: 6,
            actualBalance: 10000.00,
            notes: 'Cashier count',
            userId: $cashier->id
        );

        $this->assertNull($rec->reviewed_by);
        $this->assertNull($rec->reviewed_at);

        $reviewed = $this->reconciliationService->reviewReconciliation($rec, $manager->id);

        $this->assertSame($manager->id, $reviewed->reviewed_by);
        $this->assertNotNull($reviewed->reviewed_at);

        $this->assertDatabaseHas('register_reconciliations', [
            'id' => $rec->id,
            'reviewed_by' => $manager->id,
        ]);
    }

    // =========================================================================
    // TIER 2: BOUNDARY & VALIDATION TESTS
    // =========================================================================

    /**
     * 8. Boundary conditions: Zero recorded balance, surplus, and negative variance.
     */
    public function test_boundary_zero_balances_and_negative_variance_calculations(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(0.00, '2026-07-01');

        // Actual 0, Recorded 0 => Diff 0 Reconciled
        $rec1 = $this->reconciliationService->saveReconciliation(RegisterType::Cash, 2026, 7, 0.00, userId: $user->id);
        $this->assertSame(0.00, (float) $rec1->difference);
        $this->assertSame(ReconciliationStatus::Reconciled, $rec1->status);

        // Surplus: Actual 250.75, Recorded 0.00 => Diff +250.75 Discrepancy
        $rec2 = $this->reconciliationService->saveReconciliation(RegisterType::Cash, 2026, 8, 250.75, userId: $user->id);
        $this->assertSame(250.75, (float) $rec2->difference);
        $this->assertSame(ReconciliationStatus::Discrepancy, $rec2->status);
    }

    /**
     * 9. Upsert behavior prevents duplicate reconciliation records for the same month and register.
     */
    public function test_boundary_upsert_prevents_duplicate_reconciliations_for_same_month_and_register(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->bankLedgerService->configureBeginningBalance(50000.00, '2026-09-01');

        // First attempt: Discrepancy
        $rec1 = $this->reconciliationService->saveReconciliation(RegisterType::Bank, 2026, 9, 49000.00, 'Initial count', $user->id);
        $this->assertSame(-1000.00, (float) $rec1->difference);

        // Second attempt: Correction made, now matches
        $rec2 = $this->reconciliationService->saveReconciliation(RegisterType::Bank, 2026, 9, 50000.00, 'Corrected statement', $user->id);
        $this->assertSame($rec1->id, $rec2->id);
        $this->assertSame(0.00, (float) $rec2->difference);
        $this->assertSame(ReconciliationStatus::Reconciled, $rec2->status);

        $this->assertDatabaseCount('register_reconciliations', 1);
    }

    /**
     * 10. Empty notes and rounding precision to 2 decimal places.
     */
    public function test_boundary_empty_notes_and_precision_rounding(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(100.55, '2026-10-01');

        $rec = $this->reconciliationService->saveReconciliation(
            RegisterType::Cash,
            2026,
            10,
            actualBalance: 100.554, // should round to 100.55
            notes: null,
            userId: $user->id
        );

        $this->assertSame(0.00, (float) $rec->difference);
        $this->assertSame(ReconciliationStatus::Reconciled, $rec->status);
        $this->assertNull($rec->notes);
    }

    // =========================================================================
    // TIER 3: CROSS-FEATURE INTEGRATION TESTS
    // =========================================================================

    /**
     * 11. Subsequent transactions alter recorded balance for open month reconciliation.
     */
    public function test_cross_feature_subsequent_cash_transactions_affect_unreconciled_months(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(10000.00, '2026-11-01');

        $initialSummary = $this->reconciliationService->getMonthlySummary(RegisterType::Cash, 2026, 11);
        $this->assertSame(10000.00, (float) $initialSummary['recorded_balance']);

        // Post new cash sale in November
        $sale = Sale::create([
            'invoice_number' => 'INV-REC-1',
            'created_by' => $user->id,
            'sale_date' => Carbon::parse('2026-11-15'),
            'status' => SaleStatus::COMPLETED,
            'total' => 3500.00,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 3500.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'cash_received' => 3500.00,
            'change' => 0,
        ]);
        $this->cashLedgerService->recordCashSale($sale);

        $updatedSummary = $this->reconciliationService->getMonthlySummary(RegisterType::Cash, 2026, 11);
        $this->assertSame(13500.00, (float) $updatedSummary['recorded_balance']);
    }

    /**
     * 12. Cash Register and Bank Register maintain completely independent reconciliations.
     */
    public function test_cross_feature_bank_and_cash_registers_maintain_independent_reconciliations(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->cashLedgerService->configureBeginningBalance(10000.00, '2026-12-01');
        $this->bankLedgerService->configureBeginningBalance(80000.00, '2026-12-01');

        // Cash has discrepancy
        $cashRec = $this->reconciliationService->saveReconciliation(RegisterType::Cash, 2026, 12, 9500.00, userId: $user->id);
        $this->assertSame(ReconciliationStatus::Discrepancy, $cashRec->status);

        // Bank is reconciled
        $bankRec = $this->reconciliationService->saveReconciliation(RegisterType::Bank, 2026, 12, 80000.00, userId: $user->id);
        $this->assertSame(ReconciliationStatus::Reconciled, $bankRec->status);

        $this->assertNotSame($cashRec->id, $bankRec->id);
        $this->assertDatabaseCount('register_reconciliations', 2);
    }

    // =========================================================================
    // TIER 4: E2E WORKFLOW, ACCESS CONTROL & LIVEWIRE UI
    // =========================================================================

    /**
     * 13. Route access control for Month-End Reconciliation.
     */
    public function test_access_control_for_month_end_reconciliation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->get(route('finance.reconciliation.index'))->assertRedirect(route('login'));
        $this->actingAs($sales)->get(route('finance.reconciliation.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('finance.reconciliation.index'))->assertOk();
        $this->actingAs($admin)->get(route('finance.reconciliation.index'))->assertOk();
    }

    /**
     * 14. Livewire RegisterReconciliationManager E2E cashier/manager workflow.
     */
    public function test_livewire_reconciliation_manager_e2e_cashier_flow(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(12000.00, '2026-01-01');

        Livewire::actingAs($admin)
            ->test(RegisterReconciliationManager::class)
            ->set('activeTab', 'cash')
            ->set('selectedYear', 2026)
            ->set('selectedMonth', 1)
            ->assertSee('12,000.00') // Displays recorded balance
            ->set('actualBalance', 12000.00)
            ->set('notes', 'Physical cash count completed')
            ->call('saveReconciliation')
            ->assertSee('Reconciled') // Shows Reconciled badge
            ->assertSee('Physical cash count completed');

        $this->assertDatabaseHas('register_reconciliations', [
            'register_type' => 'cash',
            'year' => 2026,
            'month' => 1,
            'status' => 'reconciled',
        ]);

        // Now test Tab switch to Bank
        $this->bankLedgerService->configureBeginningBalance(50000.00, '2026-01-01');

        Livewire::actingAs($admin)
            ->test(RegisterReconciliationManager::class)
            ->set('activeTab', 'bank')
            ->set('selectedYear', 2026)
            ->set('selectedMonth', 1)
            ->assertSee('50,000.00')
            ->set('actualBalance', 49500.00)
            ->call('saveReconciliation')
            ->assertSee('Discrepancy');

        $this->assertDatabaseHas('register_reconciliations', [
            'register_type' => 'bank',
            'year' => 2026,
            'month' => 1,
            'status' => 'discrepancy',
            'difference' => -500.00,
        ]);
    }

    /**
     * 15. Reconciliation navigation links render for authorized users.
     */
    public function test_reconciliation_navigation_links_render_for_authorized_users(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Month-End Reconciliation');
        $this->actingAs($sales)->get(route('dashboard'))->assertDontSee('Month-End Reconciliation');
    }

    /**
     * 16. Livewire RegisterReconciliationManager handles out-of-range month inputs without ViewException.
     */
    public function test_livewire_reconciliation_manager_handles_out_of_range_month_inputs_safely(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(RegisterReconciliationManager::class)
            ->set('selectedMonth', 13)
            ->assertOk()
            ->set('selectedMonth', -1)
            ->assertOk()
            ->set('selectedMonth', 0)
            ->assertOk()
            ->set('selectedMonth', 99)
            ->assertOk();
    }
}
