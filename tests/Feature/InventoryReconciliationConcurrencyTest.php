<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\InventoryReconciliation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryReconciliationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected User $admin;

    protected SaleService $saleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'sales']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->saleService = app(SaleService::class);
    }

    /**
     * Multiple reconciliations created on the same day generate sequential numbers in REC.YYMMDD.XXXX format.
     */
    public function test_multiple_reconciliations_created_on_same_day_generate_sequential_unique_numbers(): void
    {
        $today = Carbon::parse('2026-11-16');
        Carbon::setTestNow($today);

        $sale = $this->createBaseSale();
        $saleItem = $this->createBaseSaleItem($sale);

        $expectedPrefix = 'REC.261116.';

        $recon1 = InventoryReconciliation::createWithUniqueNumber([
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'created_by' => $this->cashier->id,
        ]);

        $recon2 = InventoryReconciliation::createWithUniqueNumber([
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Item 2',
            'quantity' => 1,
            'unit_price' => 200,
            'cost_price' => 100,
            'discount' => 0,
            'subtotal' => 200,
            'created_by' => $this->cashier->id,
        ]);

        $recon3 = InventoryReconciliation::createWithUniqueNumber([
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Item 3',
            'quantity' => 1,
            'unit_price' => 300,
            'cost_price' => 150,
            'discount' => 0,
            'subtotal' => 300,
            'created_by' => $this->cashier->id,
        ]);

        $this->assertEquals($expectedPrefix.'0001', $recon1->reconciliation_number);
        $this->assertEquals($expectedPrefix.'0002', $recon2->reconciliation_number);
        $this->assertEquals($expectedPrefix.'0003', $recon3->reconciliation_number);

        Carbon::setTestNow();
    }

    /**
     * POS sale with multiple manual uncataloged items creates unique sequential reconciliations.
     */
    public function test_pos_sale_with_multiple_manual_items_generates_unique_sequential_reconciliation_numbers(): void
    {
        $today = Carbon::parse('2026-11-16');
        Carbon::setTestNow($today);

        $payload = [
            'sale_date' => '2026-11-16',
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 900.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Uncataloged Shampoo',
                    'barcode' => 'BAR-001',
                    'quantity' => 1,
                    'unit_price' => 300.00,
                    'discount' => 0,
                ],
                [
                    'is_manual' => true,
                    'name' => 'Uncataloged Conditioner',
                    'barcode' => 'BAR-002',
                    'quantity' => 1,
                    'unit_price' => 250.00,
                    'discount' => 0,
                ],
                [
                    'is_manual' => true,
                    'name' => 'Uncataloged Hair Oil',
                    'barcode' => 'BAR-003',
                    'quantity' => 1,
                    'unit_price' => 350.00,
                    'discount' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)
            ->postJson(route('sales.store'), $payload);

        $response->assertStatus(201);

        $sale = Sale::latest()->first();
        $this->assertNotNull($sale);

        $reconciliations = InventoryReconciliation::where('sale_id', $sale->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $reconciliations);
        $this->assertEquals('REC.261116.0001', $reconciliations[0]->reconciliation_number);
        $this->assertEquals('REC.261116.0002', $reconciliations[1]->reconciliation_number);
        $this->assertEquals('REC.261116.0003', $reconciliations[2]->reconciliation_number);

        Carbon::setTestNow();
    }

    /**
     * Multiple POS sales created in rapid succession never throw duplicate reconciliation_number constraint violations.
     */
    public function test_multiple_pos_sales_created_in_rapid_succession_never_collide(): void
    {
        $today = Carbon::parse('2026-11-16');
        Carbon::setTestNow($today);

        $reconciliationNumbers = [];

        for ($i = 1; $i <= 5; $i++) {
            $payload = [
                'sale_date' => '2026-11-16',
                'payment_method' => PaymentMethod::CASH->value,
                'cash_received' => 150.00,
                'status' => SaleStatus::COMPLETED->value,
                'items' => [
                    [
                        'is_manual' => true,
                        'name' => "Rapid Item {$i}",
                        'barcode' => "RAPID-{$i}",
                        'quantity' => 1,
                        'unit_price' => 150.00,
                        'discount' => 0,
                    ],
                ],
            ];

            $response = $this->actingAs($this->cashier)
                ->postJson(route('sales.store'), $payload);

            $response->assertStatus(201);

            $recon = InventoryReconciliation::where('barcode', "RAPID-{$i}")->first();
            $this->assertNotNull($recon);
            $reconciliationNumbers[] = $recon->reconciliation_number;
        }

        $this->assertCount(5, array_unique($reconciliationNumbers));
        $this->assertEquals([
            'REC.261116.0001',
            'REC.261116.0002',
            'REC.261116.0003',
            'REC.261116.0004',
            'REC.261116.0005',
        ], $reconciliationNumbers);

        Carbon::setTestNow();
    }

    /**
     * Sequence calculation finds the maximum sequence even if database IDs are out-of-order or inverted.
     */
    public function test_sequence_correctly_finds_max_even_when_ids_are_out_of_order(): void
    {
        $today = Carbon::parse('2026-11-16');
        Carbon::setTestNow($today);

        $sale = $this->createBaseSale();
        $saleItem = $this->createBaseSaleItem($sale);

        // Insert record 1 with lower sequence but higher ID later
        DB::table('inventory_reconciliations')->insert([
            'id' => 10,
            'reconciliation_number' => 'REC.261116.0005',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Existing High Sequence Item',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert record 2 with higher ID (20) but lower sequence (0002)
        DB::table('inventory_reconciliations')->insert([
            'id' => 20,
            'reconciliation_number' => 'REC.261116.0002',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Existing Low Sequence Item with High ID',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Generating next sequence must find max sequence 5 and return 0006, NOT 0003!
        $nextNumber = InventoryReconciliation::generateReconciliationNumber();
        $this->assertEquals('REC.261116.0006', $nextNumber);

        // Creating via createWithUniqueNumber must also succeed with 0006
        $newRecon = InventoryReconciliation::createWithUniqueNumber([
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'New Item After Out Of Order',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'created_by' => $this->cashier->id,
        ]);

        $this->assertEquals('REC.261116.0006', $newRecon->reconciliation_number);

        Carbon::setTestNow();
    }

    /**
     * Sequence calculation skips existing gaps safely.
     */
    public function test_sequence_generation_handles_gaps_in_existing_numbers(): void
    {
        $today = Carbon::parse('2026-11-16');
        Carbon::setTestNow($today);

        $sale = $this->createBaseSale();
        $saleItem = $this->createBaseSaleItem($sale);

        // Existing records: 0001 and 0003 (gap at 0002)
        InventoryReconciliation::create([
            'reconciliation_number' => 'REC.261116.0001',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Gap Item 1',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'status' => 'pending',
            'created_by' => $this->cashier->id,
        ]);

        InventoryReconciliation::create([
            'reconciliation_number' => 'REC.261116.0003',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Gap Item 3',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'status' => 'pending',
            'created_by' => $this->cashier->id,
        ]);

        $nextNumber = InventoryReconciliation::generateReconciliationNumber();
        $this->assertEquals('REC.261116.0004', $nextNumber);

        Carbon::setTestNow();
    }

    /**
     * createWithUniqueNumber retries when encountering a duplicate key constraint collision.
     */
    public function test_create_with_unique_number_retries_on_duplicate_constraint_violation(): void
    {
        $today = Carbon::parse('2026-11-16');
        Carbon::setTestNow($today);

        $sale = $this->createBaseSale();
        $saleItem = $this->createBaseSaleItem($sale);

        // Pre-create REC.261116.0001
        InventoryReconciliation::create([
            'reconciliation_number' => 'REC.261116.0001',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Pre-existing Record',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'created_by' => $this->cashier->id,
        ]);

        // Attempt creation with explicit stale number 'REC.261116.0001' (simulating collision)
        // createWithUniqueNumber must detect collision and regenerate with REC.261116.0002
        $recon = InventoryReconciliation::createWithUniqueNumber([
            'reconciliation_number' => 'REC.261116.0001',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Colliding Item',
            'quantity' => 1,
            'unit_price' => 150,
            'cost_price' => 75,
            'discount' => 0,
            'subtotal' => 150,
            'created_by' => $this->cashier->id,
        ]);

        $this->assertEquals('REC.261116.0002', $recon->reconciliation_number);
        $this->assertDatabaseCount('inventory_reconciliations', 2);

        Carbon::setTestNow();
    }

    /**
     * Sequence calculation handles rollover past 9999 without ordering flaw.
     */
    public function test_sequence_number_rollover_past_9999(): void
    {
        $today = Carbon::parse('2026-11-16');
        Carbon::setTestNow($today);

        $sale = $this->createBaseSale();
        $saleItem = $this->createBaseSaleItem($sale);

        InventoryReconciliation::create([
            'reconciliation_number' => 'REC.261116.9999',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Rollover Item',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'status' => 'pending',
            'created_by' => $this->cashier->id,
        ]);

        $nextNumber = InventoryReconciliation::generateReconciliationNumber();
        $this->assertEquals('REC.261116.10000', $nextNumber);

        Carbon::setTestNow();
    }

    /**
     * Different dates generate independent sequence numbers with corresponding date prefixes.
     */
    public function test_different_dates_maintain_independent_sequences(): void
    {
        $sale = $this->createBaseSale();
        $saleItem = $this->createBaseSaleItem($sale);

        $day1 = Carbon::parse('2026-11-15');
        $day2 = Carbon::parse('2026-11-16');

        $recon1 = InventoryReconciliation::createWithUniqueNumber([
            'created_at' => $day1,
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Day 1 Item',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'created_by' => $this->cashier->id,
        ]);

        $recon2 = InventoryReconciliation::createWithUniqueNumber([
            'created_at' => $day2,
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Day 2 Item',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'created_by' => $this->cashier->id,
        ]);

        $this->assertEquals('REC.261115.0001', $recon1->reconciliation_number);
        $this->assertEquals('REC.261116.0001', $recon2->reconciliation_number);
    }

    /**
     * Automatic reconciliation generation via model booting event if omitted.
     */
    public function test_model_boot_auto_generates_reconciliation_number_if_omitted(): void
    {
        $today = Carbon::parse('2026-11-16');
        Carbon::setTestNow($today);

        $sale = $this->createBaseSale();
        $saleItem = $this->createBaseSaleItem($sale);

        $recon = InventoryReconciliation::create([
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Auto Generated Number',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'created_by' => $this->cashier->id,
        ]);

        $this->assertEquals('REC.261116.0001', $recon->reconciliation_number);

        Carbon::setTestNow();
    }

    /**
     * Model boot correctly parses string created_at attribute and generates matching date prefix.
     */
    public function test_model_boot_parses_created_at_string_properly(): void
    {
        $sale = $this->createBaseSale();
        $saleItem = $this->createBaseSaleItem($sale);

        $recon = new InventoryReconciliation([
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Past Date String Item',
            'quantity' => 1,
            'unit_price' => 100,
            'cost_price' => 50,
            'discount' => 0,
            'subtotal' => 100,
            'created_by' => $this->cashier->id,
        ]);
        $recon->created_at = '2026-05-10 12:00:00';
        $recon->save();

        $this->assertEquals('REC.260510.0001', $recon->reconciliation_number);
    }

    /**
     * Historical sale edit preserves existing reconciliation numbers and generates next sequence for newly added manual items.
     */
    public function test_historical_sale_edit_preserves_reconciliations_and_generates_unique_numbers_for_new_items(): void
    {
        $today = Carbon::parse('2026-11-16');
        Carbon::setTestNow($today);

        $payload = [
            'sale_date' => '2026-11-16',
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 200.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Original Item',
                    'barcode' => 'ORIG-001',
                    'quantity' => 1,
                    'unit_price' => 200.00,
                    'discount' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson(route('sales.store'), $payload);
        $response->assertStatus(201);

        $sale = Sale::latest()->first();
        $originalRecon = InventoryReconciliation::where('sale_id', $sale->id)->first();
        $this->assertEquals('REC.261116.0001', $originalRecon->reconciliation_number);

        // Historical edit: keep original manual item and add a new manual item
        $editPayload = [
            'sale_date' => '2026-11-16',
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 500.00,
            'status' => SaleStatus::COMPLETED->value,
            'reason' => 'Customer added an additional manual item',
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Original Item',
                    'barcode' => 'ORIG-001',
                    'quantity' => 1,
                    'unit_price' => 200.00,
                    'discount' => 0,
                ],
                [
                    'is_manual' => true,
                    'name' => 'Added Later Item',
                    'barcode' => 'NEW-002',
                    'quantity' => 1,
                    'unit_price' => 300.00,
                    'discount' => 0,
                ],
            ],
        ];

        $updateResponse = $this->actingAs($this->admin)->put(route('sales.update', $sale), $editPayload);
        $updateResponse->assertRedirect(route('sales.show', $sale));

        $reconciliations = InventoryReconciliation::where('sale_id', $sale->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $reconciliations);
        $this->assertEquals('REC.261116.0001', $reconciliations[0]->reconciliation_number);
        $this->assertEquals('REC.261116.0002', $reconciliations[1]->reconciliation_number);

        Carbon::setTestNow();
    }

    /**
     * withSequenceLock executes callback and returns value cleanly across database drivers.
     */
    public function test_with_sequence_lock_executes_callback_cleanly(): void
    {
        $result = InventoryReconciliation::withSequenceLock('REC.261116.', function () {
            return 'locked_and_executed';
        });

        $this->assertEquals('locked_and_executed', $result);
    }

    private function createBaseSale(): Sale
    {
        return Sale::create([
            'invoice_number' => 'INV.'.date('ymd').'.9999',
            'customer_id' => null,
            'created_by' => $this->cashier->id,
            'sale_date' => now(),
            'status' => 'completed',
            'payment_method' => 'cash',
            'cash_amount' => 0,
            'bank_amount' => 0,
            'cash_received' => 100,
            'change' => 0,
            'subtotal' => 100,
            'global_discount' => 0,
            'coupon_discount' => 0,
            'round_off' => 0,
            'return_discount' => 0,
            'total_discount' => 0,
            'total' => 100,
        ]);
    }

    private function createBaseSaleItem(Sale $sale): SaleItem
    {
        return SaleItem::create([
            'sale_id' => $sale->id,
            'custom_name' => 'Test Item',
            'is_manual' => true,
            'quantity' => 1,
            'cost_price' => 50,
            'unit_price' => 100,
            'discount' => 0,
            'final_price' => 100,
            'subtotal' => 100,
        ]);
    }
}
