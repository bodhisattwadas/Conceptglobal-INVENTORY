<?php

namespace Tests\Feature;

use App\DTOs\SaleData;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinanceTransaction;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReturnTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $brand;

    protected Category $category;

    protected Unit $unit;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => UserRole::Admin]);
        $this->brand = Company::create(['company_name' => 'Lotus Herbals', 'short_name' => 'Lotus', 'company_code' => 'LOTUS']);
        $this->category = Category::create(['name' => 'Skin Care', 'slug' => 'skin-care']);
        $this->unit = Unit::create(['name' => 'Pieces', 'symbol' => 'pcs']);
        $this->supplier = Supplier::factory()->create();
    }

    public function test_can_fetch_customer_sales_history_by_phone(): void
    {
        $customer = Customer::create([
            'name' => 'John Customer',
            'phone' => '9876543210',
        ]);

        $product = Product::create([
            'name' => 'Face Cleanser',
            'sku' => 'SKU-FC-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 300,
            'purchase_price' => 200,
            'quantity' => 10,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-001',
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 600,
            'total_discount' => 0,
            'total' => 600,
            'cash_received' => 600,
            'change' => 0,
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'cost_price' => 200,
            'unit_price' => 300,
            'discount' => 0,
            'final_price' => 300,
            'subtotal' => 600,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'phone' => '9876543210',
        ]);

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $response->assertJsonPath('customer.name', 'John Customer');
        $response->assertJsonCount(1, 'sales');
        $response->assertJsonPath('sales.0.invoice_number', 'INV-TEST-001');
        $response->assertJsonPath('sales.0.items.0.product_name', 'Face Cleanser');
        $response->assertJsonPath('sales.0.items.0.quantity', 2);
        $response->assertJsonPath('sales.0.items.0.effective_unit_price', 300);
    }

    public function test_pos_sale_with_customer_return_restores_batch_stock_and_reduces_original_invoice(): void
    {
        $customer = Customer::create([
            'name' => 'Alice Returner',
            'phone' => '9123456789',
        ]);

        // Product A (will be returned)
        $productA = Product::create([
            'name' => 'Aloevera Gel',
            'sku' => 'SKU-AG-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 250,
            'purchase_price' => 150,
            'quantity' => 10,
        ]);

        InventoryStock::create([
            'product_id' => $productA->id,
            'quantity' => 10,
        ]);

        // Batch for Product A
        $purchase = Purchase::create([
            'invoice_number' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'purchase_date' => now()->subDays(5),
            'status' => PurchaseStatus::RECEIVED,
            'total_amount' => 1500,
        ]);

        $batchA = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productA->id,
            'batch_number' => 'BATCH-A-01',
            'quantity' => 10,
            'ordered_quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 150,
            'subtotal' => 1500,
            'location' => PurchaseLocation::SHOP,
        ]);

        // Product B (new purchase)
        $productB = Product::create([
            'name' => 'Sunscreen SPF 50',
            'sku' => 'SKU-SS-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 500,
            'purchase_price' => 300,
            'quantity' => 10,
        ]);

        InventoryStock::create([
            'product_id' => $productB->id,
            'quantity' => 10,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productB->id,
            'batch_number' => 'BATCH-B-01',
            'quantity' => 10,
            'ordered_quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 300,
            'subtotal' => 3000,
            'location' => PurchaseLocation::SHOP,
        ]);

        // Initial sale: Alice bought 2 units of Product A
        $saleService = app(SaleService::class);
        $initialSaleData = SaleData::fromArray([
            'customer_id' => $customer->id,
            'sale_date' => now()->subDay()->toDateTimeString(),
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
            'cash_received' => 500,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 2,
                    'unit_price' => 250,
                    'discount' => 0,
                ],
            ],
        ]);
        $initialSale = $saleService->createSale($initialSaleData);

        $this->assertEquals(8, $productA->fresh()->quantity);
        $this->assertEquals(8, $batchA->fresh()->received_quantity);
        $this->assertEquals(500, (float) $initialSale->fresh()->total);

        $initialSaleItem = $initialSale->items->first();

        // Now Alice comes to POS:
        // Buys 1 unit of Product B (₹500)
        // Returns 1 unit of Product A from initialSale (worth ₹250)
        // Payable should be ₹500 - ₹250 = ₹250
        $response = $this->actingAs($this->user)->postJson(route('sales.store'), [
            'customer_id' => $customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'cash_received' => 250,
            'items' => [
                [
                    'product_id' => $productB->id,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'discount' => 0,
                ],
            ],
            'return_discount' => 250,
            'returned_items' => [
                [
                    'sale_id' => $initialSale->id,
                    'sale_item_id' => $initialSaleItem->id,
                    'product_id' => $productA->id,
                    'return_qty' => 1,
                    'refund_amount' => 250,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $newSale = Sale::latest('id')->first();

        // 1. Verify New Sale financial numbers
        $this->assertEquals(500, (float) $newSale->subtotal);
        $this->assertEquals(250, (float) $newSale->return_discount);
        $this->assertEquals(250, (float) $newSale->total);
        $this->assertEquals(250, (float) $newSale->cash_received);

        // 2. Verify Product A restored to the same batch in inventory
        $this->assertEquals(9, $batchA->fresh()->received_quantity);
        $this->assertEquals(9, $productA->fresh()->quantity);
        $this->assertEquals(9, InventoryStock::where('product_id', $productA->id)->value('quantity'));

        // 3. Verify Inventory Movement logged as sale_return with batch
        $returnMovement = InventoryMovement::where('product_id', $productA->id)
            ->where('type', 'sale_return')
            ->where('purchase_item_id', $batchA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($returnMovement);
        $this->assertEquals(1, $returnMovement->quantity);
        $this->assertEquals(9, $returnMovement->balance_after);

        // 4. Verify Product A on initial invoice was decremented from 2 to 1
        $initialSaleItem->refresh();
        $this->assertEquals(1, $initialSaleItem->quantity);
        $this->assertEquals(250, (float) $initialSaleItem->subtotal);

        // 5. Verify initial invoice totals reconciled
        $initialSale->refresh();
        $this->assertEquals(250, (float) $initialSale->subtotal);
        $this->assertEquals(250, (float) $initialSale->total);

        // 6. Verify FinanceTransaction for initial invoice is updated to 250
        $financeTx = FinanceTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $initialSale->id)
            ->first();
        $this->assertNotNull($financeTx);
        $this->assertEquals(250, (float) $financeTx->amount);
    }

    public function test_customer_full_return_removes_item_from_original_invoice(): void
    {
        $customer = Customer::create([
            'name' => 'Bob FullReturn',
            'phone' => '9888877777',
        ]);

        $productA = Product::create([
            'name' => 'Rose Water',
            'sku' => 'SKU-RW-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 150,
            'purchase_price' => 80,
            'quantity' => 5,
        ]);

        InventoryStock::create([
            'product_id' => $productA->id,
            'quantity' => 5,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-TEST-002',
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'purchase_date' => now()->subDays(3),
            'status' => PurchaseStatus::RECEIVED,
            'total_amount' => 800,
        ]);

        $batch = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productA->id,
            'batch_number' => 'BATCH-ROSE-01',
            'quantity' => 5,
            'ordered_quantity' => 5,
            'received_quantity' => 5,
            'unit_price' => 80,
            'subtotal' => 400,
            'location' => PurchaseLocation::SHOP,
        ]);

        $productB = Product::create([
            'name' => 'Moisturizer',
            'sku' => 'SKU-MOIST-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 400,
            'purchase_price' => 250,
            'quantity' => 10,
        ]);

        InventoryStock::create([
            'product_id' => $productB->id,
            'quantity' => 10,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productB->id,
            'batch_number' => 'BATCH-MOIST-01',
            'quantity' => 10,
            'ordered_quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 250,
            'subtotal' => 2500,
            'location' => PurchaseLocation::SHOP,
        ]);

        // Initial sale: 1 unit of Product A sold
        $saleService = app(SaleService::class);
        $initialSale = $saleService->createSale(SaleData::fromArray([
            'customer_id' => $customer->id,
            'sale_date' => now()->subDay()->toDateTimeString(),
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
            'cash_received' => 150,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 1,
                    'unit_price' => 150,
                    'discount' => 0,
                ],
            ],
        ]));

        $this->assertEquals(4, $productA->fresh()->quantity);
        $this->assertEquals(4, $batch->fresh()->received_quantity);

        $initialSaleItemId = $initialSale->items->first()->id;

        // Bob returns the 1 unit of Product A, and buys Product B (₹400)
        // Payable: 400 - 150 = 250
        $response = $this->actingAs($this->user)->postJson(route('sales.store'), [
            'customer_id' => $customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'cash_received' => 250,
            'items' => [
                [
                    'product_id' => $productB->id,
                    'quantity' => 1,
                    'unit_price' => 400,
                    'discount' => 0,
                ],
            ],
            'return_discount' => 150,
            'returned_items' => [
                [
                    'sale_id' => $initialSale->id,
                    'sale_item_id' => $initialSaleItemId,
                    'product_id' => $productA->id,
                    'return_qty' => 1,
                    'refund_amount' => 150,
                ],
            ],
        ]);

        $response->assertStatus(201);

        // Product A is returned to batch
        $this->assertEquals(5, $batch->fresh()->received_quantity);
        $this->assertEquals(5, $productA->fresh()->quantity);

        // Original sale item is removed from corresponding invoice
        $this->assertDatabaseMissing('sale_items', ['id' => $initialSaleItemId]);

        // Original invoice subtotal and total become 0
        $initialSale->refresh();
        $this->assertEquals(0, (float) $initialSale->total);
        $this->assertStringContainsString('Returned', $initialSale->notes);
    }

    public function test_return_discount_exceeding_subtotal_caps_total_payable_at_zero(): void
    {
        $customer = Customer::create([
            'name' => 'Charlie HighReturn',
            'phone' => '9777766666',
        ]);

        $productA = Product::create([
            'name' => 'Expensive Cream',
            'sku' => 'SKU-EC-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 1000,
            'purchase_price' => 600,
            'quantity' => 5,
        ]);

        InventoryStock::create([
            'product_id' => $productA->id,
            'quantity' => 5,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-TEST-003',
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'purchase_date' => now()->subDays(2),
            'status' => PurchaseStatus::RECEIVED,
            'total_amount' => 3000,
        ]);

        $batch = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productA->id,
            'batch_number' => 'BATCH-EXP-01',
            'quantity' => 5,
            'ordered_quantity' => 5,
            'received_quantity' => 5,
            'unit_price' => 600,
            'subtotal' => 3000,
            'location' => PurchaseLocation::SHOP,
        ]);

        $productB = Product::create([
            'name' => 'Small Lip Balm',
            'sku' => 'SKU-LB-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 200,
            'purchase_price' => 100,
            'quantity' => 10,
        ]);

        InventoryStock::create([
            'product_id' => $productB->id,
            'quantity' => 10,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productB->id,
            'batch_number' => 'BATCH-LB-01',
            'quantity' => 10,
            'ordered_quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 100,
            'subtotal' => 1000,
            'location' => PurchaseLocation::SHOP,
        ]);

        $saleService = app(SaleService::class);
        $initialSale = $saleService->createSale(SaleData::fromArray([
            'customer_id' => $customer->id,
            'sale_date' => now()->subDay()->toDateTimeString(),
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
            'cash_received' => 1000,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'discount' => 0,
                ],
            ],
        ]));

        $initialSaleItemId = $initialSale->items->first()->id;

        // Buying ₹200 item, but returning ₹1000 item. Return discount is 1000.
        // Total payable is max(0, 200 - 1000) = 0
        $response = $this->actingAs($this->user)->postJson(route('sales.store'), [
            'customer_id' => $customer->id,
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'cash_received' => 0,
            'items' => [
                [
                    'product_id' => $productB->id,
                    'quantity' => 1,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
            'return_discount' => 1000,
            'returned_items' => [
                [
                    'sale_id' => $initialSale->id,
                    'sale_item_id' => $initialSaleItemId,
                    'product_id' => $productA->id,
                    'return_qty' => 1,
                    'refund_amount' => 1000,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $newSale = Sale::latest('id')->first();
        $this->assertEquals(0, (float) $newSale->total);
        $this->assertEquals(1000, (float) $newSale->return_discount);
    }

    public function test_multiple_sequential_partial_returns_do_not_over_restore_batch(): void
    {
        $customer = Customer::create([
            'name' => 'Dan DoubleReturn',
            'phone' => '9666655555',
        ]);

        $product = Product::create([
            'name' => 'Toner Mist',
            'sku' => 'SKU-TM-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 200,
            'purchase_price' => 100,
            'quantity' => 10,
        ]);

        InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-TEST-004',
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'purchase_date' => now()->subDays(2),
            'status' => PurchaseStatus::RECEIVED,
            'total_amount' => 1000,
        ]);

        $batch = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'BATCH-TM-01',
            'quantity' => 10,
            'ordered_quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 100,
            'subtotal' => 1000,
            'location' => PurchaseLocation::SHOP,
        ]);

        $saleService = app(SaleService::class);
        // Dan buys 3 units of Toner Mist (stock becomes 7)
        $initialSale = $saleService->createSale(SaleData::fromArray([
            'customer_id' => $customer->id,
            'sale_date' => now()->subDay()->toDateTimeString(),
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
            'cash_received' => 600,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]));

        $this->assertEquals(7, $batch->fresh()->received_quantity);
        $this->assertEquals(7, $product->fresh()->quantity);

        $initialItem = $initialSale->items->first();

        // 1st Return: Dan returns 1 unit
        $saleService->createSale(SaleData::fromArray([
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateTimeString(),
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
            'cash_received' => 0,
            'return_discount' => 200,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
            'returned_items' => [
                [
                    'sale_id' => $initialSale->id,
                    'sale_item_id' => $initialItem->id,
                    'product_id' => $product->id,
                    'return_qty' => 1,
                    'refund_amount' => 200,
                ],
            ],
        ]));

        // Stock restored from 7 to 8, then 1 sold in the new sale => stock stays 7 net, batch receives 1 (7 -> 8) then deducts 1 (8 -> 7)
        $this->assertEquals(7, $batch->fresh()->received_quantity);
        $this->assertEquals(2, $initialItem->fresh()->quantity);

        // 2nd Return: Dan returns another 1 unit from the same original line item
        $saleService->createSale(SaleData::fromArray([
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateTimeString(),
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
            'cash_received' => 0,
            'return_discount' => 200,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
            'returned_items' => [
                [
                    'sale_id' => $initialSale->id,
                    'sale_item_id' => $initialItem->id,
                    'product_id' => $product->id,
                    'return_qty' => 1,
                    'refund_amount' => 200,
                ],
            ],
        ]));

        $this->assertEquals(1, $initialItem->fresh()->quantity);
        $this->assertEquals(200, (float) $initialSale->fresh()->total);
    }

    public function test_can_fetch_sales_history_for_guest_sale_with_phone_in_notes(): void
    {
        $product = Product::create([
            'name' => 'Hair Oil',
            'sku' => 'SKU-HO-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 180,
            'purchase_price' => 100,
            'quantity' => 10,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-GUEST-001',
            'customer_id' => null,
            'created_by' => $this->user->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 180,
            'total_discount' => 0,
            'total' => 180,
            'cash_received' => 180,
            'change' => 0,
            'notes' => '[Guest]: Phone: 9555544444',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 100,
            'unit_price' => 180,
            'discount' => 0,
            'final_price' => 180,
            'subtotal' => 180,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'phone' => '9555544444',
        ]);

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $response->assertJsonCount(1, 'sales');
        $response->assertJsonPath('sales.0.invoice_number', 'INV-GUEST-001');
    }

    public function test_can_fetch_sales_history_by_invoice_number(): void
    {
        $customer = Customer::create([
            'name' => 'Charlie InvoiceSearch',
            'phone' => '9444433333',
        ]);

        $product = Product::create([
            'name' => 'Conditioner',
            'sku' => 'SKU-COND-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 350,
            'purchase_price' => 200,
            'quantity' => 10,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-TARGET-999',
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 350,
            'total_discount' => 0,
            'total' => 350,
            'cash_received' => 350,
            'change' => 0,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 200,
            'unit_price' => 350,
            'discount' => 0,
            'final_price' => 350,
            'subtotal' => 350,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'invoice_number' => 'INV-TARGET-999',
        ]);

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $response->assertJsonPath('customer.name', 'Charlie InvoiceSearch');
        $response->assertJsonCount(1, 'sales');
        $response->assertJsonPath('sales.0.invoice_number', 'INV-TARGET-999');
        $response->assertJsonPath('sales.0.items.0.product_name', 'Conditioner');

        // Can also search using query / partial invoice number
        $response2 = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'query' => 'TARGET-999',
        ]);

        $response2->assertOk();
        $response2->assertJsonPath('found', true);
        $response2->assertJsonPath('sales.0.invoice_number', 'INV-TARGET-999');
    }

    public function test_can_fetch_guest_sale_by_invoice_number(): void
    {
        $product = Product::create([
            'name' => 'Hair Oil',
            'sku' => 'SKU-HO-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 220,
            'purchase_price' => 120,
            'quantity' => 10,
        ]);

        $guestSale = Sale::create([
            'invoice_number' => 'INV-GUEST-888',
            'customer_id' => null,
            'created_by' => $this->user->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 220,
            'total_discount' => 0,
            'total' => 220,
            'cash_received' => 220,
            'change' => 0,
            'notes' => 'Walk-in guest customer',
        ]);

        SaleItem::create([
            'sale_id' => $guestSale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 120,
            'unit_price' => 220,
            'discount' => 0,
            'final_price' => 220,
            'subtotal' => 220,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'invoice_number' => 'INV-GUEST-888',
        ]);

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $response->assertJsonCount(1, 'sales');
        $response->assertJsonPath('sales.0.invoice_number', 'INV-GUEST-888');
        $response->assertJsonPath('sales.0.items.0.product_name', 'Hair Oil');
    }
}
