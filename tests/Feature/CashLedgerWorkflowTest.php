<?php

namespace Tests\Feature;

use App\DTOs\SaleData;
use App\Enums\CashTransactionType;
use App\Enums\FinanceCategoryType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Livewire\Finance\CashLedgerTable;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\FinanceCategory;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\CashLedgerService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class CashLedgerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected CashLedgerService $cashLedgerService;

    protected SaleService $saleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashLedgerService = app(CashLedgerService::class);
        $this->saleService = app(SaleService::class);
    }

    public function test_cash_sales_automatically_create_cash_ledger_inflow(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::create([
            'name' => 'Alice Doe',
            'phone' => '9876543210',
        ]);

        $sale = $this->createPendingSale($user, total: 1530.00, customer: $customer, method: PaymentMethod::CASH);

        // When pending, no cash ledger transaction should exist yet
        $this->assertDatabaseMissing('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);

        // Complete sale with payment
        $response = $this->actingAs($user)->patch(route('sales.complete', $sale), [
            'cash_received' => 2000.00,
        ]);

        $response->assertSessionHasNoErrors();
        $sale->refresh();

        $this->assertSame(SaleStatus::COMPLETED, $sale->status);
        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'transaction_type' => CashTransactionType::CashSale->value,
            'amount' => 1530.00,
            'name' => 'Alice Doe',
            'split_account' => 'Product Sales',
            'reference_number' => $sale->invoice_number,
        ]);

        $cashTxn = CashTransaction::where('reference_type', Sale::class)->where('reference_id', $sale->id)->first();
        $this->assertNotNull($cashTxn);
        $this->assertSame(1530.00, $cashTxn->signed_amount);
    }

    public function test_non_cash_sales_do_not_create_cash_ledger_entries(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        // Test UPI
        $upiSale = $this->createPendingSale($user, total: 850.00, method: PaymentMethod::UPI);
        $this->actingAs($user)->patch(route('sales.complete', $upiSale));

        // Test Card
        $cardSale = $this->createPendingSale($user, total: 1200.00, method: PaymentMethod::CARD);
        $this->actingAs($user)->patch(route('sales.complete', $cardSale));

        // Test Bank Transfer
        $transferSale = $this->createPendingSale($user, total: 3000.00, method: PaymentMethod::TRANSFER);
        $this->actingAs($user)->patch(route('sales.complete', $transferSale));

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_updating_payment_method_from_non_cash_to_cash_and_vice_versa(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        // 1. Create completed UPI sale
        $sale = $this->createPendingSale($user, total: 500.00, method: PaymentMethod::UPI);
        $this->saleService->completeSale($sale);

        $this->assertDatabaseMissing('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);

        // 2. Change payment method from UPI to CASH
        $this->saleService->updatePaymentMethod($sale, PaymentMethod::CASH, cashReceived: 500.00);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'transaction_type' => CashTransactionType::CashSale->value,
            'amount' => 500.00,
        ]);

        // 3. Change payment method back from CASH to UPI
        $this->saleService->updatePaymentMethod($sale, PaymentMethod::UPI);

        $this->assertDatabaseMissing('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);
    }

    public function test_cancelling_and_deleting_cash_sale_voids_cash_ledger_entry(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        // 1. Test Cancellation
        $sale1 = $this->createPendingSale($user, total: 750.00, method: PaymentMethod::CASH);
        $this->saleService->completeSale($sale1, ['cash_received' => 750.00]);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale1->id,
        ]);

        $this->saleService->cancelSale($sale1, 'Customer requested cancellation');

        $this->assertDatabaseMissing('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale1->id,
        ]);

        // 2. Test Permanent Deletion
        $sale2 = $this->createPendingSale($user, total: 950.00, method: PaymentMethod::CASH);
        $this->saleService->completeSale($sale2, ['cash_received' => 950.00]);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale2->id,
        ]);

        $this->saleService->deleteSale($sale2);

        $this->assertDatabaseMissing('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale2->id,
        ]);
    }

    public function test_recording_cash_investment_increases_cash_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(10000.00);

        $txn = $this->cashLedgerService->recordCashInvestment([
            'transaction_date' => now()->toDateString(),
            'amount' => 5000.00,
            'name' => 'John Investor',
            'reference_number' => 'INV-001',
            'description' => 'Seed Capital Injection',
            'split_account' => 'Owner Capital',
        ], $user->id);

        $this->assertSame(CashTransactionType::CashInvestment, $txn->transaction_type);
        $this->assertSame(5000.00, $txn->signed_amount);

        $ledger = $this->cashLedgerService->getLedgerEntries();
        $this->assertSame(10000.00, $ledger['beginning_balance']);
        $this->assertSame(5000.00, $ledger['total_inflows']);
        $this->assertSame(0.00, $ledger['total_outflows']);
        $this->assertSame(15000.00, $ledger['ending_balance']);
    }

    public function test_recording_expense_decreases_cash_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(20000.00);

        $category = FinanceCategory::create([
            'name' => 'Utilities',
            'slug' => 'utilities',
            'type' => FinanceCategoryType::Expense,
        ]);

        $txn = $this->cashLedgerService->recordExpense([
            'transaction_date' => now()->toDateString(),
            'amount' => 1950.00,
            'name' => 'Electric Company',
            'reference_number' => 'EXP-UTIL-01',
            'finance_category_id' => $category->id,
            'description' => 'Monthly electricity bill',
        ], $user->id);

        $this->assertSame(CashTransactionType::Expense, $txn->transaction_type);
        $this->assertSame(-1950.00, $txn->signed_amount);
        $this->assertSame('Utilities', $txn->split_account);

        $ledger = $this->cashLedgerService->getLedgerEntries();
        $this->assertSame(20000.00, $ledger['beginning_balance']);
        $this->assertSame(0.00, $ledger['total_inflows']);
        $this->assertSame(1950.00, $ledger['total_outflows']);
        $this->assertSame(18050.00, $ledger['ending_balance']);
    }

    public function test_recording_drawing_decreases_cash_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->cashLedgerService->configureBeginningBalance(15000.00);

        $txn = $this->cashLedgerService->recordDrawing([
            'transaction_date' => now()->toDateString(),
            'amount' => 2500.00,
            'name' => 'Business Owner',
            'reference_number' => 'DRW-001',
            'description' => 'Owner personal withdrawal',
        ], $user->id);

        $this->assertSame(CashTransactionType::Drawing, $txn->transaction_type);
        $this->assertSame(-2500.00, $txn->signed_amount);
        $this->assertSame('Owner Drawings', $txn->split_account);

        $ledger = $this->cashLedgerService->getLedgerEntries();
        $this->assertSame(15000.00, $ledger['beginning_balance']);
        $this->assertSame(0.00, $ledger['total_inflows']);
        $this->assertSame(2500.00, $ledger['total_outflows']);
        $this->assertSame(12500.00, $ledger['ending_balance']);
    }

    public function test_validation_prevents_zero_or_negative_amounts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        // Service validation tests
        $this->expectException(InvalidArgumentException::class);
        $this->cashLedgerService->recordCashInvestment(['amount' => 0]);

        $this->expectException(InvalidArgumentException::class);
        $this->cashLedgerService->recordExpense(['amount' => -100]);

        $this->expectException(InvalidArgumentException::class);
        $this->cashLedgerService->recordDrawing(['amount' => 0]);
    }

    public function test_livewire_component_validates_positive_amounts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($user)
            ->test(CashLedgerTable::class)
            ->set('inv_amount', 0)
            ->call('saveInvestment')
            ->assertHasErrors(['inv_amount' => 'gt'])
            ->set('exp_amount', -50)
            ->call('saveExpense')
            ->assertHasErrors(['exp_amount' => 'gt'])
            ->set('draw_amount', 0)
            ->call('saveDrawing')
            ->assertHasErrors(['draw_amount' => 'gt']);
    }

    public function test_running_balance_calculates_chronologically(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        // Baseline Beginning Balance: 50,000.00
        $this->cashLedgerService->configureBeginningBalance(50000.00, '2026-01-01');

        // Sequential transactions:
        // Row 1: 2026-01-05 Expense: -5,000.00 => Running Balance: 45,000.00
        $this->cashLedgerService->recordExpense([
            'transaction_date' => '2026-01-05',
            'amount' => 5000.00,
            'name' => 'Katherine Anne',
            'split_account' => 'Contract labor',
        ], $user->id);

        // Row 2: 2026-01-08 Expense: -3,475.00 => Running Balance: 41,525.00
        $this->cashLedgerService->recordExpense([
            'transaction_date' => '2026-01-08',
            'amount' => 3475.00,
            'name' => 'The Food Source',
            'split_account' => 'Inventory',
        ], $user->id);

        // Row 3: 2026-01-15 Cash Sale: +1,500.00 => Running Balance: 43,025.00
        $sale = $this->createPendingSale($user, total: 1500.00, method: PaymentMethod::CASH);
        $sale->update(['sale_date' => Carbon::parse('2026-01-15')]);
        $this->saleService->completeSale($sale, ['cash_received' => 1500.00]);

        // Row 4: 2026-01-20 Drawing: -2,000.00 => Running Balance: 41,025.00
        $this->cashLedgerService->recordDrawing([
            'transaction_date' => '2026-01-20',
            'amount' => 2000.00,
            'name' => 'Owner',
        ], $user->id);

        // Row 5: 2026-01-25 Cash Investment: +10,000.00 => Running Balance: 51,025.00
        $this->cashLedgerService->recordCashInvestment([
            'transaction_date' => '2026-01-25',
            'amount' => 10000.00,
            'name' => 'Angel Investor',
        ], $user->id);

        $ledger = $this->cashLedgerService->getLedgerEntries('2026-01-01', '2026-01-31');

        $this->assertSame(50000.00, $ledger['beginning_balance']);
        $this->assertCount(5, $ledger['entries']);

        $balances = $ledger['entries']->pluck('running_balance')->all();
        $this->assertSame([
            45000.00,
            41525.00,
            43025.00,
            41025.00,
            51025.00,
        ], $balances);

        $this->assertSame(11500.00, $ledger['total_inflows']);
        $this->assertSame(10475.00, $ledger['total_outflows']);
        $this->assertSame(51025.00, $ledger['ending_balance']);
    }

    public function test_access_control_for_cash_ledger(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        // 1. Unauthenticated gets redirected to login
        $this->get(route('finance.cash-ledger.index'))
            ->assertRedirect(route('login'));

        // 2. Sales role is forbidden (403)
        $this->actingAs($sales)
            ->get(route('finance.cash-ledger.index'))
            ->assertForbidden();

        // 3. Manager can access (200)
        $this->actingAs($manager)
            ->get(route('finance.cash-ledger.index'))
            ->assertOk()
            ->assertSee('Cash &amp; Cash Equivalent', false);

        // 4. Admin can access (200)
        $this->actingAs($admin)
            ->get(route('finance.cash-ledger.index'))
            ->assertOk()
            ->assertSee('1100 Cash and Cash Equivalent');
    }

    public function test_livewire_cash_ledger_table_modal_workflows_and_manual_deletion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Configure beginning balance via modal
        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->set('setting_balance', 35000.00)
            ->set('setting_date', '2026-01-01')
            ->call('saveBeginningBalance')
            ->assertSee('35,000.00');

        $this->assertSame('35000.00', Setting::get('cash_beginning_balance'));

        // Record investment via modal
        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->set('inv_date', '2026-02-01')
            ->set('inv_amount', 12000.00)
            ->set('inv_name', 'Founder Fund')
            ->set('inv_num', 'DEP-777')
            ->set('inv_split', 'Owner Investment')
            ->set('inv_description', 'Initial funding round')
            ->call('saveInvestment')
            ->assertSee('DEP-777')
            ->assertSee('12,000.00');

        $manualTxn = CashTransaction::where('reference_number', 'DEP-777')->first();
        $this->assertNotNull($manualTxn);
        $this->assertNull($manualTxn->reference_type);

        // Delete manual transaction
        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->call('deleteTransaction', $manualTxn->id)
            ->assertDontSee('DEP-777');

        $this->assertDatabaseMissing('cash_transactions', ['id' => $manualTxn->id]);

        // Attempt deleting automated cash sale transaction should fail
        $sale = $this->createPendingSale($admin, total: 1000.00, method: PaymentMethod::CASH);
        $this->saleService->completeSale($sale, ['cash_received' => 1000.00]);

        $saleTxn = CashTransaction::where('reference_type', Sale::class)->where('reference_id', $sale->id)->first();
        $this->assertNotNull($saleTxn);

        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->call('deleteTransaction', $saleTxn->id)
            ->assertSee('Automated sales transactions cannot be deleted');

        $this->assertDatabaseHas('cash_transactions', ['id' => $saleTxn->id]);
    }

    public function test_sale_date_update_synchronizes_transaction_date_in_cash_ledger(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $sale = $this->createPendingSale($user, total: 600.00, method: PaymentMethod::CASH);
        $this->saleService->completeSale($sale, ['cash_received' => 600.00]);

        $newDate = Carbon::parse('2026-05-20');
        $this->saleService->updateSaleDate($sale, $newDate);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'transaction_date' => '2026-05-20',
        ]);
    }

    public function test_date_filtering_accurately_recalculates_period_beginning_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        // Baseline on 2026-01-01: 10,000.00
        $this->cashLedgerService->configureBeginningBalance(10000.00, '2026-01-01');

        // Prior transaction: 2026-01-10 Cash Investment: +5,000.00
        $this->cashLedgerService->recordCashInvestment([
            'transaction_date' => '2026-01-10',
            'amount' => 5000.00,
        ], $user->id);

        // Prior transaction: 2026-01-15 Expense: -2,000.00
        $this->cashLedgerService->recordExpense([
            'transaction_date' => '2026-01-15',
            'amount' => 2000.00,
        ], $user->id);

        // Current period transaction: 2026-02-05 Expense: -1,000.00
        $this->cashLedgerService->recordExpense([
            'transaction_date' => '2026-02-05',
            'amount' => 1000.00,
        ], $user->id);

        // When requesting window starting at 2026-02-01:
        // Period Beginning Balance should be 10,000 + 5,000 - 2,000 = 13,000.00
        $ledger = $this->cashLedgerService->getLedgerEntries('2026-02-01', '2026-02-28');

        $this->assertSame(13000.00, $ledger['beginning_balance']);
        $this->assertCount(1, $ledger['entries']);
        $this->assertSame(12000.00, $ledger['ending_balance']);
    }

    public function test_navigation_link_renders_in_menu_for_admin_and_manager(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertSee('Cash &amp; Cash Equivalent', false);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertSee('Cash &amp; Cash Equivalent', false);

        $this->actingAs($sales)
            ->get(route('dashboard'))
            ->assertDontSee('Cash &amp; Cash Equivalent', false);
    }

    public function test_updating_historical_cash_sale_with_new_customer_updates_cash_transaction_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customerA = Customer::create(['name' => 'Alice Customer', 'phone' => '1111111111']);
        $customerB = Customer::create(['name' => 'Bob Customer', 'phone' => '2222222222']);

        $sale = $this->createPendingSale($admin, total: 500.00, customer: $customerA, method: PaymentMethod::CASH);
        $this->saleService->completeSale($sale, ['cash_received' => 500.00]);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_id' => $sale->id,
            'name' => 'Alice Customer',
        ]);

        // 1. Reassign to Customer B
        $saleData = new SaleData(
            sale_date: now(),
            payment_method: PaymentMethod::CASH,
            created_by: $admin->id,
            items: [],
            customer_id: $customerB->id,
            cash_received: 500.00,
            change: 0,
            status: SaleStatus::COMPLETED,
        );

        $this->saleService->updateHistoricalSale($sale, $saleData);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_id' => $sale->id,
            'name' => 'Bob Customer',
        ]);

        // 2. Reassign to walk-in (null customer)
        $walkInData = new SaleData(
            sale_date: now(),
            payment_method: PaymentMethod::CASH,
            created_by: $admin->id,
            items: [],
            customer_id: null,
            cash_received: 500.00,
            change: 0,
            status: SaleStatus::COMPLETED,
        );

        $this->saleService->updateHistoricalSale($sale, $walkInData);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_id' => $sale->id,
            'name' => 'Walk-in Customer',
        ]);
    }

    public function test_today_filter_and_boundary_dates_in_ledger_entries_and_livewire(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $today = now()->toDateString();

        // Record investment today
        $this->cashLedgerService->recordCashInvestment([
            'transaction_date' => $today,
            'amount' => 5000.00,
            'description' => 'Today Inflow Entry',
            'name' => 'Today Donor',
        ], $admin->id);

        // 1. getLedgerEntries with identical boundary start/end date
        $ledger = $this->cashLedgerService->getLedgerEntries($today, $today);
        $this->assertCount(1, $ledger['entries']);
        $this->assertSame(5000.00, $ledger['total_inflows']);
        $this->assertSame('Today Inflow Entry', $ledger['entries']->first()->description);

        // 2. Livewire component with preset 'today' filter
        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->set('dateFilter', 'today')
            ->assertSee('Today Inflow Entry')
            ->assertSee('Today Donor')
            ->assertSee('5,000.00');

        // 3. Exact boundary query on historical single-day range
        $this->cashLedgerService->recordExpense([
            'transaction_date' => '2026-03-31',
            'amount' => 1250.00,
            'description' => 'Month End Closing Bill',
            'name' => 'Landlord Corp',
        ], $admin->id);

        $monthEndLedger = $this->cashLedgerService->getLedgerEntries('2026-03-01', '2026-03-31');
        $this->assertTrue($monthEndLedger['entries']->contains('description', 'Month End Closing Bill'));

        $singleDayLedger = $this->cashLedgerService->getLedgerEntries('2026-03-31', '2026-03-31');
        $this->assertCount(1, $singleDayLedger['entries']);
        $this->assertSame('Month End Closing Bill', $singleDayLedger['entries']->first()->description);
    }

    public function test_clearing_beginning_balance_date_properly_unsets_setting(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // 1. Configure baseline with explicit date
        $this->cashLedgerService->configureBeginningBalance(10000.00, '2026-01-01');
        $this->assertSame('10000.00', Setting::get('cash_beginning_balance'));
        $this->assertSame('2026-01-01', Setting::get('cash_beginning_date'));

        // 2. Clear date using null
        $this->cashLedgerService->configureBeginningBalance(12000.00, null);
        $this->assertSame('12000.00', Setting::get('cash_beginning_balance'));
        $this->assertNull(Setting::get('cash_beginning_date'));

        // 3. Set date again and clear using empty string ""
        $this->cashLedgerService->configureBeginningBalance(15000.00, '2026-02-01');
        $this->assertSame('2026-02-01', Setting::get('cash_beginning_date'));

        $this->cashLedgerService->configureBeginningBalance(15000.00, '');
        $this->assertNull(Setting::get('cash_beginning_date'));

        // 4. Test clearing via Livewire beginning balance modal with empty date string
        $this->cashLedgerService->configureBeginningBalance(20000.00, '2026-03-01');
        $this->assertSame('2026-03-01', Setting::get('cash_beginning_date'));

        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->set('setting_balance', '25000.00')
            ->set('setting_date', '')
            ->call('saveBeginningBalance')
            ->assertHasNoErrors();

        $this->assertSame('25000.00', Setting::get('cash_beginning_balance'));
        $this->assertNull(Setting::get('cash_beginning_date'));
    }

    public function test_cash_ledger_sync_command_backfills_completed_cash_sales(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        // Create completed cash sales directly in database without ledger transactions
        $sale1 = Sale::create([
            'invoice_number' => 'INV-HIST-0001',
            'created_by' => $user->id,
            'sale_date' => '2026-01-10',
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 600.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 600.00,
            'cash_received' => 600.00,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH,
        ]);

        $sale2 = Sale::create([
            'invoice_number' => 'INV-HIST-0002',
            'created_by' => $user->id,
            'sale_date' => '2026-01-15',
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 950.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 950.00,
            'cash_received' => 1000.00,
            'change' => 50.00,
            'payment_method' => PaymentMethod::CASH,
        ]);

        // Pending cash sale (should NOT be synced)
        $salePending = Sale::create([
            'invoice_number' => 'INV-HIST-0003',
            'created_by' => $user->id,
            'sale_date' => '2026-01-20',
            'status' => SaleStatus::PENDING,
            'subtotal' => 400.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 400.00,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH,
        ]);

        // Completed UPI sale (should NOT be synced into cash ledger)
        $saleUpi = Sale::create([
            'invoice_number' => 'INV-HIST-0004',
            'created_by' => $user->id,
            'sale_date' => '2026-01-22',
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1200.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 1200.00,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::UPI,
        ]);

        // Verify no cash transactions exist prior to running sync
        $this->assertDatabaseMissing('cash_transactions', ['reference_id' => $sale1->id]);
        $this->assertDatabaseMissing('cash_transactions', ['reference_id' => $sale2->id]);

        // Execute artisan command
        $this->artisan('cash-ledger:sync')
            ->expectsOutput('Starting Cash Ledger Synchronization...')
            ->expectsOutput('Successfully synchronized 2 cash sale transactions into the cash ledger.')
            ->assertSuccessful();

        // Verify synced cash transactions
        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale1->id,
            'amount' => 600.00,
            'transaction_type' => CashTransactionType::CashSale->value,
            'reference_number' => 'INV-HIST-0001',
        ]);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale2->id,
            'amount' => 950.00,
            'transaction_type' => CashTransactionType::CashSale->value,
            'reference_number' => 'INV-HIST-0002',
        ]);

        // Verify pending and UPI sales were excluded
        $this->assertDatabaseMissing('cash_transactions', ['reference_id' => $salePending->id]);
        $this->assertDatabaseMissing('cash_transactions', ['reference_id' => $saleUpi->id]);
    }

    public function test_livewire_manual_transaction_editing_updates_transaction_and_recomputes_running_balances(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Configure starting balance
        $this->cashLedgerService->configureBeginningBalance(1000.00, '2026-01-01');

        // Create an investment and an expense
        $investment = $this->cashLedgerService->recordCashInvestment([
            'transaction_date' => '2026-01-05',
            'amount' => 5000.00,
            'name' => 'Initial Partner',
            'reference_number' => 'INV-01',
            'description' => 'Original Capital',
        ], $admin->id);

        $expense = $this->cashLedgerService->recordExpense([
            'transaction_date' => '2026-01-10',
            'amount' => 1200.00,
            'name' => 'Original Vendor',
            'reference_number' => 'EXP-01',
            'description' => 'Original Rent',
            'split_account' => 'Rent Expense',
        ], $admin->id);

        // Initial balance: 1000 + 5000 - 1200 = 4800
        $ledger = $this->cashLedgerService->getLedgerEntries();
        $this->assertSame(4800.00, $ledger['ending_balance']);

        // Test editing expense via Livewire
        $component = Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->call('editTransaction', $expense->id)
            ->assertSet('showEditModal', true)
            ->assertSet('edit_transaction_id', $expense->id)
            ->assertSet('edit_transaction_type', CashTransactionType::Expense->value)
            ->assertSet('edit_name', 'Original Vendor')
            ->assertSet('edit_reference_number', 'EXP-01')
            ->assertSet('edit_description', 'Original Rent');

        // Verify invalid amount is rejected
        $component->set('edit_amount', '-50.00')
            ->call('updateTransaction')
            ->assertHasErrors(['edit_amount' => 'gt']);

        // Correct amount to 1500.00 and update details
        $component->set('edit_amount', '1500.00')
            ->set('edit_name', 'Updated Landlord')
            ->set('edit_description', 'Updated Rent Payment')
            ->set('edit_reference_number', 'EXP-01-UPD')
            ->call('updateTransaction')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        // Verify database updated
        $expense->refresh();
        $this->assertSame('1500.00', (string) $expense->amount);
        $this->assertSame('Updated Landlord', $expense->name);
        $this->assertSame('Updated Rent Payment', $expense->description);
        $this->assertSame('EXP-01-UPD', $expense->reference_number);

        // Recomputed balance: 1000 + 5000 - 1500 = 4500
        $updatedLedger = $this->cashLedgerService->getLedgerEntries();
        $this->assertSame(4500.00, $updatedLedger['ending_balance']);

        // Test editing investment: increase to 7000.00
        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->call('editTransaction', $investment->id)
            ->assertSet('showEditModal', true)
            ->assertSet('edit_transaction_type', CashTransactionType::CashInvestment->value)
            ->set('edit_amount', '7000.00')
            ->set('edit_name', 'Senior Partner')
            ->call('updateTransaction')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $investment->refresh();
        $this->assertSame('7000.00', (string) $investment->amount);
        $this->assertSame('Senior Partner', $investment->name);

        // Recomputed balance: 1000 + 7000 - 1500 = 6500
        $finalLedger = $this->cashLedgerService->getLedgerEntries();
        $this->assertSame(6500.00, $finalLedger['ending_balance']);
    }

    public function test_attempting_to_edit_or_update_automated_cash_sales_fails_or_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Create completed cash sale
        $sale = $this->createPendingSale($admin, total: 850.00, method: PaymentMethod::CASH);
        $this->actingAs($admin)->patch(route('sales.complete', $sale), [
            'cash_received' => 1000.00,
        ]);

        $automatedTxn = CashTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->firstOrFail();

        // 1. Livewire editTransaction should be blocked and not open modal
        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->call('editTransaction', $automatedTxn->id)
            ->assertSet('showEditModal', false)
            ->assertSee('Automated sales transactions cannot be modified directly');

        // 2. Direct Livewire updateTransaction call with automated ID should be blocked
        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->set('edit_transaction_id', $automatedTxn->id)
            ->set('edit_date', '2026-02-01')
            ->set('edit_amount', '9999.00')
            ->call('updateTransaction')
            ->assertSet('showEditModal', false)
            ->assertSee('Automated sales transactions cannot be modified directly');

        $automatedTxn->refresh();
        $this->assertSame('850.00', (string) $automatedTxn->amount);

        // 3. Service level exception check
        $this->expectException(InvalidArgumentException::class);
        $this->cashLedgerService->updateManualTransaction($automatedTxn, [
            'amount' => 9999.00,
        ]);
    }

    public function test_livewire_cash_ledger_table_handles_malformed_start_date_without_view_exception(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(CashLedgerTable::class)
            ->set('startDate', 'garbage-non-date-value')
            ->assertOk()
            ->assertSee('-');
    }

    private function createPendingSale(User $user, float $total, ?Customer $customer = null, PaymentMethod $method = PaymentMethod::CASH): Sale
    {
        return Sale::create([
            'invoice_number' => 'INV-TEST-'.str_pad((string) (Sale::count() + 1), 4, '0', STR_PAD_LEFT),
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
