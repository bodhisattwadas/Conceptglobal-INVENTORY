<?php

namespace Tests\Feature;

use App\Enums\BankTransactionType;
use App\Enums\CashTransactionType;
use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\BankTransaction;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\DiscountCoupon;
use App\Models\Sale;
use App\Models\User;
use App\Services\BankLedgerService;
use App\Services\CashLedgerService;
use App\Services\SaleService;
use App\Services\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected SaleService $saleService;

    protected CashLedgerService $cashLedgerService;

    protected BankLedgerService $bankLedgerService;

    protected SalesReportService $salesReportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saleService = app(SaleService::class);
        $this->cashLedgerService = app(CashLedgerService::class);
        $this->bankLedgerService = app(BankLedgerService::class);
        $this->salesReportService = app(SalesReportService::class);
    }

    public function test_split_sale_creation_stores_cash_and_bank_amounts_and_posts_dual_ledgers(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'cash_amount' => 1000.00,
            'bank_amount' => 250.00,
            'cash_received' => 1000.00,
            'total' => 1250.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Haircut and Grooming Package',
                    'quantity' => 1,
                    'unit_price' => 1250.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('sales.store'), $payload);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $sale = Sale::first();
        $this->assertNotNull($sale);
        $this->assertEquals(PaymentMethod::SPLIT, $sale->payment_method);
        $this->assertEquals(1250.00, (float) $sale->total);
        $this->assertEquals(1000.00, (float) $sale->cash_amount);
        $this->assertEquals(250.00, (float) $sale->bank_amount);
        $this->assertTrue($sale->isSplit());
        $this->assertEquals(1000.00, $sale->getCashAmount());
        $this->assertEquals(250.00, $sale->getBankAmount());

        // Verify Cash Ledger
        $cashTx = CashTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->first();
        $this->assertNotNull($cashTx);
        $this->assertEquals(1000.00, (float) $cashTx->amount);
        $this->assertEquals(CashTransactionType::CashSale, $cashTx->transaction_type);
        $this->assertStringContainsString('Cash Portion (Split)', $cashTx->description);
        $this->assertStringContainsString($sale->invoice_number, $cashTx->description);

        // Verify Bank Ledger
        $bankTx = BankTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->first();
        $this->assertNotNull($bankTx);
        $this->assertEquals(250.00, (float) $bankTx->amount);
        $this->assertEquals(BankTransactionType::UpiSale, $bankTx->transaction_type);
        $this->assertStringContainsString('UPI Portion (Split)', $bankTx->description);
        $this->assertStringContainsString($sale->invoice_number, $bankTx->description);
    }

    public function test_split_sale_validation_rejects_unbalanced_amounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'cash_amount' => 1000.00,
            'bank_amount' => 200.00, // Sum 1200 != Total 1250
            'cash_received' => 1000.00,
            'total' => 1250.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Haircut Service',
                    'quantity' => 1,
                    'unit_price' => 1250.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('sales.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cash_amount', 'bank_amount', 'payment_method']);
    }

    public function test_pending_split_sale_does_not_post_to_ledgers_until_completed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $sale = Sale::create([
            'invoice_number' => 'INV-SPLIT-PENDING-001',
            'created_by' => $admin->id,
            'sale_date' => now(),
            'status' => SaleStatus::PENDING,
            'subtotal' => 1250.00,
            'total_discount' => 0.00,
            'total' => 1250.00,
            'payment_method' => PaymentMethod::SPLIT,
            'cash_amount' => 1000.00,
            'bank_amount' => 250.00,
            'cash_received' => 0.00,
            'change' => 0.00,
        ]);

        // When pending, neither ledger should have transactions
        $this->assertDatabaseMissing('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);
        $this->assertDatabaseMissing('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);

        // Complete the sale
        $this->saleService->completeSale($sale, ['cash_received' => 1000.00]);

        $sale->refresh();
        $this->assertEquals(SaleStatus::COMPLETED, $sale->status);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 1000.00,
        ]);

        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 250.00,
        ]);
    }

    public function test_split_sale_cash_received_change_calculation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'cash_amount' => 1000.00,
            'bank_amount' => 250.00,
            'cash_received' => 1500.00, // ₹500 change returned
            'total' => 1250.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Haircut and Styling',
                    'quantity' => 1,
                    'unit_price' => 1250.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('sales.store'), $payload);

        $response->assertStatus(201);

        $sale = Sale::first();
        $this->assertNotNull($sale);
        $this->assertEquals(1500.00, (float) $sale->cash_received);
        $this->assertEquals(500.00, (float) $sale->change);

        // Ledger must record the cash portion (1000), not the customer tender (1500)
        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 1000.00,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 250.00,
        ]);
    }

    public function test_cancelling_split_sale_voids_both_cash_and_bank_ledger_entries(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $sale = Sale::create([
            'invoice_number' => 'INV-SPLIT-CANCEL-001',
            'created_by' => $admin->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1250.00,
            'total_discount' => 0.00,
            'total' => 1250.00,
            'payment_method' => PaymentMethod::SPLIT,
            'cash_amount' => 1000.00,
            'bank_amount' => 250.00,
            'cash_received' => 1000.00,
            'change' => 0.00,
        ]);

        $this->cashLedgerService->recordCashSale($sale);
        $this->bankLedgerService->recordSaleTransaction($sale);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);

        // Cancel the sale
        $this->saleService->cancelSale($sale, 'Customer requested refund/cancellation');

        $this->assertDatabaseMissing('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);
        $this->assertDatabaseMissing('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);
    }

    public function test_ensure_sales_synced_backfills_missing_split_sales_in_both_ledgers(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $sale = Sale::create([
            'invoice_number' => 'INV-SPLIT-SYNC-001',
            'created_by' => $admin->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1250.00,
            'total_discount' => 0.00,
            'total' => 1250.00,
            'payment_method' => PaymentMethod::SPLIT,
            'cash_amount' => 1000.00,
            'bank_amount' => 250.00,
            'cash_received' => 1000.00,
            'change' => 0.00,
        ]);

        $this->assertDatabaseMissing('cash_transactions', [
            'reference_id' => $sale->id,
        ]);
        $this->assertDatabaseMissing('bank_transactions', [
            'reference_id' => $sale->id,
        ]);

        // Run sync commands
        $syncedCash = $this->cashLedgerService->ensureSalesSynced();
        $syncedBank = $this->bankLedgerService->ensureSalesSynced();

        $this->assertGreaterThanOrEqual(1, $syncedCash);
        $this->assertGreaterThanOrEqual(1, $syncedBank);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 1000.00,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 250.00,
        ]);
    }

    public function test_sales_print_receipt_and_show_view_render_split_payment_breakdown(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::create([
            'name' => 'John Split',
            'phone' => '9988776655',
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-SPLIT-VIEW-001',
            'customer_id' => $customer->id,
            'created_by' => $admin->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1250.00,
            'total_discount' => 0.00,
            'total' => 1250.00,
            'payment_method' => PaymentMethod::SPLIT,
            'cash_amount' => 1000.00,
            'bank_amount' => 250.00,
            'cash_received' => 1200.00,
            'change' => 200.00,
        ]);

        // Test Print View
        $printResponse = $this->actingAs($admin)->get(route('sales.print', $sale));
        $printResponse->assertOk();
        $printResponse->assertSee('Cash Paid');
        $printResponse->assertSee('UPI / Bank Paid');
        $printResponse->assertSee('Change Returned');

        // Test Show View
        $showResponse = $this->actingAs($admin)->get(route('sales.show', $sale));
        $showResponse->assertOk();
        $showResponse->assertSee('Split (Cash + UPI)');
        $showResponse->assertSee('Split Tender Allocation');
        $showResponse->assertSee('Cash Register Inflow');
        $showResponse->assertSee('Bank Register Inflow (UPI)');
    }

    public function test_sales_report_service_includes_split_sales_in_breakdowns(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Sale::create([
            'invoice_number' => 'INV-SPLIT-REPORT-001',
            'created_by' => $admin->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1250.00,
            'total_discount' => 0.00,
            'total' => 1250.00,
            'payment_method' => PaymentMethod::SPLIT,
            'cash_amount' => 1000.00,
            'bank_amount' => 250.00,
            'cash_received' => 1000.00,
            'change' => 0.00,
        ]);

        $report = $this->salesReportService->getReportData(now()->startOfDay(), now()->endOfDay());

        // Check payment methods breakdown
        $this->assertArrayHasKey('split', $report['payment_methods']);
        $this->assertEquals(1250.00, $report['payment_methods']['split']['amount']);
        $this->assertEquals(1, $report['payment_methods']['split']['count']);

        // Check daily breakdown attribution
        $daily = collect($report['daily_breakdown']);
        $todayRow = $daily->firstWhere('date', now()->format('Y-m-d'));
        $this->assertNotNull($todayRow);
        $this->assertEquals(1000.00, $todayRow['cash']);
        $this->assertEquals(250.00, $todayRow['upi']);
    }

    public function test_split_sale_with_discount_and_round_off_succeeds(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Simulates POS payload with global discount and round_off (omitting 'total' or including accurate total)
        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'cash_amount' => 500.00,
            'bank_amount' => 400.00,
            'cash_received' => 500.00,
            'global_discount' => 100.00,
            'round_off' => 0.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Premium Service Package',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('sales.store'), $payload);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $sale = Sale::first();
        $this->assertNotNull($sale);
        $this->assertEquals(900.00, (float) $sale->total);
        $this->assertEquals(500.00, (float) $sale->cash_amount);
        $this->assertEquals(400.00, (float) $sale->bank_amount);

        // Verify Dual Ledgers
        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 500.00,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 400.00,
        ]);
    }

    public function test_pos_split_sale_with_auto_round_off_down_succeeds(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Subtotal ₹1000.50 auto-rounded down to ₹1000.00 with round_off: -0.50
        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'total' => 1000.00,
            'cash_amount' => 600.00,
            'bank_amount' => 400.00,
            'cash_received' => 600.00,
            'round_off' => -0.50,
            'bill_discount' => 0.50,
            'global_discount' => 0.50,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Haircut and Grooming',
                    'quantity' => 1,
                    'unit_price' => 1000.50,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('sales.store'), $payload);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $sale = Sale::first();
        $this->assertNotNull($sale);
        $this->assertEquals(1000.00, (float) $sale->total);
        $this->assertEquals(600.00, (float) $sale->cash_amount);
        $this->assertEquals(400.00, (float) $sale->bank_amount);

        // Verify Dual Ledgers
        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 600.00,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 400.00,
        ]);
    }

    public function test_pos_split_sale_with_auto_round_off_up_succeeds(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Subtotal ₹1000.30 auto-rounded up to ₹1001.00 with round_off: 0.70
        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'total' => 1001.00,
            'cash_amount' => 501.00,
            'bank_amount' => 500.00,
            'cash_received' => 501.00,
            'round_off' => 0.70,
            'bill_discount' => -0.70,
            'global_discount' => -0.70,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Styling Treatment',
                    'quantity' => 1,
                    'unit_price' => 1000.30,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('sales.store'), $payload);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $sale = Sale::first();
        $this->assertNotNull($sale);
        $this->assertEquals(1001.00, (float) $sale->total);
        $this->assertEquals(501.00, (float) $sale->cash_amount);
        $this->assertEquals(500.00, (float) $sale->bank_amount);

        // Verify Dual Ledgers
        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 501.00,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 500.00,
        ]);
    }

    public function test_pos_split_sale_with_discount_coupon_and_round_off_succeeds(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $coupon = DiscountCoupon::create([
            'code' => 'SAVE100',
            'type' => CouponType::Fixed,
            'value' => 100,
            'min_purchase_amount' => 500,
            'is_active' => true,
        ]);

        // Item ₹1000.30, coupon ₹100.00, round_off -0.30 -> Total ₹900.00
        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'coupon_id' => $coupon->id,
            'total' => 900.00,
            'cash_amount' => 500.00,
            'bank_amount' => 400.00,
            'cash_received' => 500.00,
            'round_off' => -0.30,
            'bill_discount' => 0.30,
            'global_discount' => 100.30,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Spa Package',
                    'quantity' => 1,
                    'unit_price' => 1000.30,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('sales.store'), $payload);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $sale = Sale::first();
        $this->assertNotNull($sale);
        $this->assertEquals(900.00, (float) $sale->total);
        $this->assertEquals(500.00, (float) $sale->cash_amount);
        $this->assertEquals(400.00, (float) $sale->bank_amount);

        // Verify Dual Ledgers
        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 500.00,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 400.00,
        ]);
    }

    public function test_pos_split_sale_with_cashier_manual_bill_discount_succeeds(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Subtotal ₹1000.00 with manual cashier discount ₹50.00 sent as round_off: -50.00, total: 950.00
        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'total' => 950.00,
            'cash_amount' => 550.00,
            'bank_amount' => 400.00,
            'cash_received' => 550.00,
            'round_off' => -50.00,
            'bill_discount' => 50.00,
            'global_discount' => 50.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Hair Coloring',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('sales.store'), $payload);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $sale = Sale::first();
        $this->assertNotNull($sale);
        $this->assertEquals(950.00, (float) $sale->total);
        $this->assertEquals(550.00, (float) $sale->cash_amount);
        $this->assertEquals(400.00, (float) $sale->bank_amount);

        // Verify Dual Ledgers
        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 550.00,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 400.00,
        ]);
    }

    public function test_split_sale_rejects_tampered_total_mismatch(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Item price is 5000, but client passes tampered total of 100 with split 60+40
        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'total' => 100.00,
            'cash_amount' => 60.00,
            'bank_amount' => 40.00,
            'cash_received' => 60.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Expensive Package',
                    'quantity' => 1,
                    'unit_price' => 5000.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson(route('sales.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['total', 'cash_amount', 'bank_amount', 'payment_method']);

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertDatabaseCount('bank_transactions', 0);
    }

    public function test_update_payment_method_to_split_updates_cash_and_bank_amounts_and_ledgers(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $sale = Sale::create([
            'invoice_number' => 'INV-SPLIT-UPDATE-001',
            'created_by' => $admin->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1000.00,
            'total_discount' => 0.00,
            'total' => 1000.00,
            'payment_method' => PaymentMethod::CASH,
            'cash_amount' => 0.00,
            'bank_amount' => 0.00,
            'cash_received' => 1000.00,
            'change' => 0.00,
        ]);

        $this->cashLedgerService->recordCashSale($sale);

        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 1000.00,
        ]);
        $this->assertDatabaseMissing('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);

        // Transition from CASH to SPLIT via controller endpoint
        $response = $this->actingAs($admin)->patch(route('sales.update-payment-method', $sale), [
            'payment_method' => PaymentMethod::SPLIT->value,
            'cash_amount' => 600.00,
            'bank_amount' => 400.00,
            'cash_received' => 600.00,
            'reason' => 'Customer split tender after transaction',
        ]);

        $response->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertEquals(PaymentMethod::SPLIT, $sale->payment_method);
        $this->assertEquals(600.00, (float) $sale->cash_amount);
        $this->assertEquals(400.00, (float) $sale->bank_amount);
        $this->assertEquals(600.00, (float) $sale->cash_received);

        // Verify Cash Ledger adjusted to 600.00
        $this->assertDatabaseHas('cash_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 600.00,
        ]);

        // Verify Bank Ledger created for 400.00
        $this->assertDatabaseHas('bank_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'amount' => 400.00,
        ]);
    }

    public function test_update_split_sale_rejects_tampered_total_mismatch(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $sale = Sale::create([
            'invoice_number' => 'INV-SPLIT-UPDATE-TAMPER-001',
            'created_by' => $admin->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 5000.00,
            'total_discount' => 0.00,
            'total' => 5000.00,
            'payment_method' => PaymentMethod::SPLIT,
            'cash_amount' => 3000.00,
            'bank_amount' => 2000.00,
            'cash_received' => 3000.00,
            'change' => 0.00,
        ]);

        $payload = [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::SPLIT->value,
            'total' => 100.00, // Tampered total
            'cash_amount' => 60.00,
            'bank_amount' => 40.00,
            'cash_received' => 60.00,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Expensive Package',
                    'quantity' => 1,
                    'unit_price' => 5000.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->putJson(route('sales.update', $sale), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['total', 'cash_amount', 'bank_amount', 'payment_method']);

        $sale->refresh();
        $this->assertEquals(5000.00, (float) $sale->total);
        $this->assertEquals(3000.00, (float) $sale->cash_amount);
        $this->assertEquals(2000.00, (float) $sale->bank_amount);
    }

    public function test_update_payment_method_to_split_rejects_unbalanced_amounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $sale = Sale::create([
            'invoice_number' => 'INV-SPLIT-UNBALANCED-001',
            'created_by' => $admin->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1000.00,
            'total_discount' => 0.00,
            'total' => 1000.00,
            'payment_method' => PaymentMethod::CASH,
            'cash_amount' => 0.00,
            'bank_amount' => 0.00,
            'cash_received' => 1000.00,
            'change' => 0.00,
        ]);

        $response = $this->actingAs($admin)->patchJson(route('sales.update-payment-method', $sale), [
            'payment_method' => PaymentMethod::SPLIT->value,
            'cash_amount' => 500.00,
            'bank_amount' => 300.00, // Sum 800 != Total 1000
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'The total split payment amounts (₹800) must equal the sale total (₹1000) and be greater than zero.']);

        $sale->refresh();
        $this->assertEquals(PaymentMethod::CASH, $sale->payment_method);
    }
}
