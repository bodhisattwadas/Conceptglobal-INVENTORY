<?php

namespace Tests\Feature;

use App\DTOs\SaleData;
use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\SaleException;
use App\Livewire\Sales\SalesTable;
use App\Models\Category;
use App\Models\DiscountCoupon;
use App\Models\FinanceTransaction;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaleDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $salesUser;

    protected Product $product;

    protected Category $category;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->salesUser = User::factory()->create(['role' => 'sales']);

        $this->category = Category::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
        $this->unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);

        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Shampoo Bottle',
            'purchase_price' => 100,
            'selling_price' => 200,
            'quantity' => 20,
            'is_active' => true,
        ]);

        $this->createStockWithBatch($this->product, 20);
    }

    private function createStockWithBatch(Product $product, int $quantity): void
    {
        $supplier = Supplier::factory()->create();
        $purchase = Purchase::create([
            'invoice_number' => 'PO.TEST.'.uniqid(),
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'created_by' => $this->admin->id,
            'total' => $product->purchase_price * $quantity,
        ]);

        $batch = $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'received_quantity' => $quantity,
            'unit_price' => $product->purchase_price,
            'selling_price' => $product->selling_price,
            'subtotal' => $product->purchase_price * $quantity,
            'location' => 'shop',
        ]);

        $stock = InventoryStock::firstOrCreate(['product_id' => $product->id], ['quantity' => 0]);
        $stock->update(['quantity' => $quantity]);
    }

    public function test_deleting_a_completed_sale_returns_product_to_inventory_and_clears_traces(): void
    {
        $saleService = app(SaleService::class);

        $initialProductQty = $this->product->fresh()->quantity;
        $initialStockQty = InventoryStock::where('product_id', $this->product->id)->first()->quantity;
        $batch = $this->product->purchaseItems()->first();
        $initialBatchQty = $batch->received_quantity;

        // 1. Create a coupon and completed sale of 3 units
        $coupon = DiscountCoupon::create([
            'code' => 'SAVE10',
            'name' => 'Save 10',
            'type' => CouponType::Fixed,
            'value' => 10,
            'is_active' => true,
            'used_count' => 0,
        ]);

        $saleData = SaleData::fromArray([
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::COMPLETED->value,
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 600,
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'created_by' => $this->salesUser->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $sale = $saleService->createSale($saleData);

        // Verify stock deducted
        $this->assertEquals($initialProductQty - 3, $this->product->fresh()->quantity);
        $this->assertEquals($initialStockQty - 3, InventoryStock::where('product_id', $this->product->id)->first()->quantity);
        $this->assertEquals($initialBatchQty - 3, $batch->fresh()->received_quantity);
        $this->assertEquals(1, $coupon->fresh()->used_count);

        // Verify finance and movements exist
        $this->assertDatabaseHas('finance_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);
        $this->assertTrue(InventoryMovement::where('sale_id', $sale->id)->exists());

        $saleId = $sale->id;
        $invoiceNumber = $sale->invoice_number;

        // 2. Delete the sale
        $saleService->deleteSale($sale);

        // 3. Verify products returned to inventory!
        $this->assertEquals($initialProductQty, $this->product->fresh()->quantity);
        $this->assertEquals($initialStockQty, InventoryStock::where('product_id', $this->product->id)->first()->quantity);
        $this->assertEquals($initialBatchQty, $batch->fresh()->received_quantity);

        // 4. Verify coupon usage restored
        $this->assertEquals(0, $coupon->fresh()->used_count);

        // 5. Verify all traces deleted
        $this->assertDatabaseMissing('sales', ['id' => $saleId]);
        $this->assertDatabaseMissing('sale_items', ['sale_id' => $saleId]);
        $this->assertDatabaseMissing('finance_transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $saleId,
        ]);
        $this->assertFalse(InventoryMovement::where('sale_id', $saleId)->exists());
        $this->assertFalse(InventoryMovement::where('reference', $invoiceNumber)->exists());
    }

    public function test_direct_model_delete_returns_product_to_inventory(): void
    {
        $saleService = app(SaleService::class);

        $initialProductQty = $this->product->fresh()->quantity;
        $batch = $this->product->purchaseItems()->first();
        $initialBatchQty = $batch->received_quantity;

        $saleData = SaleData::fromArray([
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::COMPLETED->value,
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 400,
            'created_by' => $this->salesUser->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $sale = $saleService->createSale($saleData);

        $this->assertEquals($initialProductQty - 2, $this->product->fresh()->quantity);
        $this->assertEquals($initialBatchQty - 2, $batch->fresh()->received_quantity);

        $saleId = $sale->id;

        // Call $sale->delete() directly
        $sale->delete();

        // Verify product stock is restored
        $this->assertEquals($initialProductQty, $this->product->fresh()->quantity);
        $this->assertEquals($initialBatchQty, $batch->fresh()->received_quantity);
        $this->assertDatabaseMissing('sales', ['id' => $saleId]);
        $this->assertDatabaseMissing('sale_items', ['sale_id' => $saleId]);
    }

    public function test_deleting_cancelled_sale_does_not_double_restore_stock(): void
    {
        $saleService = app(SaleService::class);

        $initialProductQty = $this->product->fresh()->quantity;
        $batch = $this->product->purchaseItems()->first();
        $initialBatchQty = $batch->received_quantity;

        $saleData = SaleData::fromArray([
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::COMPLETED->value,
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 400,
            'created_by' => $this->salesUser->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $sale = $saleService->createSale($saleData);

        // Cancel sale (which restores stock)
        $saleService->cancelSale($sale, 'Customer cancellation');
        $this->assertEquals($initialProductQty, $this->product->fresh()->quantity);
        $this->assertEquals($initialBatchQty, $batch->fresh()->received_quantity);

        // Now delete the cancelled sale
        $saleService->deleteSale($sale);

        // Verify stock is still original (NOT double restored!)
        $this->assertEquals($initialProductQty, $this->product->fresh()->quantity);
        $this->assertEquals($initialBatchQty, $batch->fresh()->received_quantity);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
    }

    public function test_controller_permanent_delete_returns_product_to_inventory(): void
    {
        $saleService = app(SaleService::class);

        $initialProductQty = $this->product->fresh()->quantity;

        $saleData = SaleData::fromArray([
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::COMPLETED->value,
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 200,
            'created_by' => $this->salesUser->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $sale = $saleService->createSale($saleData);
        $this->assertEquals($initialProductQty - 1, $this->product->fresh()->quantity);

        $response = $this->actingAs($this->admin)->delete(route('sales.destroy', ['sale' => $sale, 'permanent' => 1]));

        $response->assertRedirect(route('sales.index'));
        $this->assertEquals($initialProductQty, $this->product->fresh()->quantity);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
    }

    public function test_sales_table_livewire_component_deletes_sale_and_restores_inventory(): void
    {
        $saleService = app(SaleService::class);

        $initialProductQty = $this->product->fresh()->quantity;

        $saleData = SaleData::fromArray([
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::COMPLETED->value,
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 400,
            'created_by' => $this->salesUser->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $sale = $saleService->createSale($saleData);
        $this->assertEquals($initialProductQty - 2, $this->product->fresh()->quantity);

        Livewire::actingAs($this->admin)
            ->test(SalesTable::class)
            ->call('delete', $sale->id)
            ->assertDispatched('toast');

        $this->assertEquals($initialProductQty, $this->product->fresh()->quantity);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
    }

    public function test_error_during_sale_creation_leaves_zero_traces_and_inventory_reverts(): void
    {
        $saleService = app(SaleService::class);

        $initialProductQty = $this->product->fresh()->quantity;
        $initialStockQty = InventoryStock::where('product_id', $this->product->id)->first()->quantity;
        $batch = $this->product->purchaseItems()->first();
        $initialBatchQty = $batch->received_quantity;

        $initialSalesCount = Sale::count();
        $initialMovementsCount = InventoryMovement::count();
        $initialFinanceCount = FinanceTransaction::count();

        // Attempt to create sale where quantity requested exceeds available stock (or invalid discount)
        try {
            $saleData = SaleData::fromArray([
                'sale_date' => now()->toDateString(),
                'status' => SaleStatus::COMPLETED->value,
                'payment_method' => PaymentMethod::CASH->value,
                'cash_received' => 100, // Insufficient cash
                'created_by' => $this->salesUser->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 5,
                        'unit_price' => 200,
                        'discount' => 0,
                    ],
                ],
            ]);

            $saleService->createSale($saleData);
            $this->fail('Expected SaleException to be thrown');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(SaleException::class, $e);
        }

        // Verify inventory is completely intact
        $this->assertEquals($initialProductQty, $this->product->fresh()->quantity);
        $this->assertEquals($initialStockQty, InventoryStock::where('product_id', $this->product->id)->first()->quantity);
        $this->assertEquals($initialBatchQty, $batch->fresh()->received_quantity);

        // Verify zero traces left
        $this->assertEquals($initialSalesCount, Sale::count());
        $this->assertEquals($initialMovementsCount, InventoryMovement::count());
        $this->assertEquals($initialFinanceCount, FinanceTransaction::count());
    }

    public function test_api_sale_store_reverts_inventory_and_deletes_traces_on_error(): void
    {
        $initialProductQty = $this->product->fresh()->quantity;
        $initialSalesCount = Sale::count();

        // Send invalid request (e.g. cash payment with insufficient cash tendered)
        $response = $this->actingAs($this->salesUser)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::COMPLETED->value,
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 50, // Less than total
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                ],
            ],
        ]);

        $response->assertStatus(400);

        // Inventory must remain untouched
        $this->assertEquals($initialProductQty, $this->product->fresh()->quantity);
        $this->assertEquals($initialSalesCount, Sale::count());
    }
}
