<?php

namespace Tests\Feature;

use App\DTOs\SaleData;
use App\DTOs\SaleItemData;
use App\Enums\PaymentMethod;
use App\Livewire\Inventory\StockAdjustmentModal;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InventoryAdjustment;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected Company $brand;

    protected Category $category;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Apex Vendor',
            'email' => 'apex@test.com',
        ]);

        $this->brand = Company::create([
            'company_code' => 'BRD-APEX',
            'company_name' => 'Apex Brand',
            'short_name' => 'Apex',
        ]);
        $this->supplier->companies()->attach($this->brand->id);

        $this->category = Category::create(['name' => 'Skincare', 'slug' => 'skincare']);
        $this->unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
    }

    public function test_stock_reallocation_moves_quantity_from_source_to_target_and_creates_fifo_batch(): void
    {
        $service = app(InventoryAdjustmentService::class);

        // Product X: 6 units in master
        $productX = Product::factory()->create([
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sku' => 'SKU-PROD-X',
            'product_code' => 'CODE-X',
            'name' => 'Cream Shade X',
            'mrp' => 500,
            'purchase_price' => 250,
            'selling_price' => 500,
            'quantity' => 6,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $productX->id, 'quantity' => 6]);

        // Product Y: 0 units in master
        $productY = Product::factory()->create([
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sku' => 'SKU-PROD-Y',
            'product_code' => 'CODE-Y',
            'name' => 'Cream Shade Y',
            'mrp' => 550,
            'purchase_price' => 0,
            'selling_price' => 550,
            'quantity' => 0,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $productY->id, 'quantity' => 0]);

        // PO received with 6 units of Product X
        $purchase = Purchase::create([
            'invoice_number' => 'PO-ADJ-001',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'location' => 'shop',
            'purchase_date' => now()->toDateString(),
            'total' => 1500,
            'created_by' => $this->user->id,
        ]);

        $batchX = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productX->id,
            'quantity' => 6,
            'received_quantity' => 6,
            'unit_price' => 250,
            'selling_price' => 500,
            'batch_number' => 'BATCH-261029-X',
            'subtotal' => 1500,
        ]);

        // Reallocate 3 units from X to Y
        $adjustment = $service->reallocateStock([
            'source_product_id' => $productX->id,
            'source_purchase_item_id' => $batchX->id,
            'target_product_id' => $productY->id,
            'quantity' => 3,
            'target_mrp' => 550,
            'reason' => 'misallocation_split',
            'notes' => 'Received 3 units of Shade Y under Shade X line item',
        ], $this->user->id);

        $this->assertInstanceOf(InventoryAdjustment::class, $adjustment);
        $this->assertEquals('reallocation', $adjustment->type);
        $this->assertEquals(3, $adjustment->quantity);
        $this->assertEquals(250, (float) $adjustment->unit_cost);

        // Check Product X state
        $productX->refresh();
        $batchX->refresh();
        $stockX = InventoryStock::where('product_id', $productX->id)->first();

        $this->assertEquals(3, $productX->quantity);
        $this->assertEquals(3, $stockX->quantity);
        $this->assertEquals(3, $batchX->received_quantity);

        // Check Product Y state
        $productY->refresh();
        $stockY = InventoryStock::where('product_id', $productY->id)->first();
        $batchY = PurchaseItem::where('product_id', $productY->id)->first();

        $this->assertEquals(3, $productY->quantity);
        $this->assertEquals(3, $stockY->quantity);
        $this->assertNotNull($batchY);
        $this->assertEquals(3, $batchY->received_quantity);
        $this->assertEquals(250, (float) $batchY->unit_price);
        $this->assertEquals('BATCH-261029-X-R', $batchY->batch_number);
        $this->assertEquals($purchase->id, $batchY->purchase_id);

        // Check Inventory Movements
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $productX->id,
            'type' => 'reallocation_out',
            'quantity' => -3,
            'balance_after' => 3,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $productY->id,
            'type' => 'reallocation_in',
            'quantity' => 3,
            'balance_after' => 3,
        ]);
    }

    public function test_reallocated_product_can_be_sold_at_pos_and_deducts_fifo_batch_without_error(): void
    {
        $service = app(InventoryAdjustmentService::class);
        $saleService = app(SaleService::class);

        $productX = Product::factory()->create([
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sku' => 'SKU-SPLIT-X',
            'name' => 'Split Shampoo X',
            'mrp' => 400,
            'purchase_price' => 200,
            'selling_price' => 400,
            'quantity' => 6,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $productX->id, 'quantity' => 6]);

        $productY = Product::factory()->create([
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sku' => 'SKU-SPLIT-Y',
            'name' => 'Split Conditioner Y',
            'mrp' => 420,
            'purchase_price' => 0,
            'selling_price' => 420,
            'quantity' => 0,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $productY->id, 'quantity' => 0]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-POS-SPLIT',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'location' => 'shop',
            'purchase_date' => now()->toDateString(),
            'total' => 1200,
            'created_by' => $this->user->id,
        ]);

        $batchX = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productX->id,
            'quantity' => 6,
            'received_quantity' => 6,
            'unit_price' => 200,
            'selling_price' => 400,
            'batch_number' => 'BATCH-SPLIT-1',
            'subtotal' => 1200,
        ]);

        // Reallocate 3 units to Y
        $service->reallocateStock([
            'source_product_id' => $productX->id,
            'source_purchase_item_id' => $batchX->id,
            'target_product_id' => $productY->id,
            'quantity' => 3,
            'reason' => 'misallocation_split',
        ], $this->user->id);

        $customer = Customer::create([
            'name' => 'POS Customer',
            'phone' => '9876543210',
        ]);

        // Sell 2 units of Product Y at POS
        $saleData = new SaleData(
            sale_date: now(),
            payment_method: PaymentMethod::CASH,
            created_by: $this->user->id,
            items: [
                new SaleItemData(
                    product_id: $productY->id,
                    quantity: 2,
                    unit_price: 420,
                    discount: 0,
                ),
            ],
            customer_id: $customer->id,
            cash_received: 840,
            change: 0,
        );

        $sale = $saleService->createSale($saleData);
        $this->assertNotNull($sale);

        // Verify Product Y inventory after POS sale
        $productY->refresh();
        $stockY = InventoryStock::where('product_id', $productY->id)->first();
        $batchY = PurchaseItem::where('product_id', $productY->id)->first();

        $this->assertEquals(1, $productY->quantity);
        $this->assertEquals(1, $stockY->quantity);
        $this->assertEquals(1, $batchY->received_quantity);

        // Verify Product X still has 3 units
        $productX->refresh();
        $this->assertEquals(3, $productX->quantity);
    }

    public function test_quick_product_creation_and_reallocation(): void
    {
        $service = app(InventoryAdjustmentService::class);

        $productX = Product::factory()->create([
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Parent Product',
            'quantity' => 5,
            'mrp' => 300,
        ]);
        InventoryStock::create(['product_id' => $productX->id, 'quantity' => 5]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-QUICK-01',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'location' => 'shop',
            'purchase_date' => now()->toDateString(),
            'total' => 750,
            'created_by' => $this->user->id,
        ]);

        $batchX = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productX->id,
            'quantity' => 5,
            'received_quantity' => 5,
            'unit_price' => 150,
            'selling_price' => 300,
            'batch_number' => 'BATCH-Q-1',
            'subtotal' => 750,
        ]);

        // 1. Create quick product
        $quickProduct = $service->createQuickProduct([
            'name' => 'Newly Created Product Y',
            'product_code' => 'NEW-CODE-Y',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'mrp' => 350,
        ], $this->user->id);

        $this->assertDatabaseHas('products', ['id' => $quickProduct->id, 'name' => 'Newly Created Product Y']);
        $this->assertEquals(0, $quickProduct->quantity);

        // 2. Reallocate 2 units to the newly created product
        $adjustment = $service->reallocateStock([
            'source_product_id' => $productX->id,
            'source_purchase_item_id' => $batchX->id,
            'target_product_id' => $quickProduct->id,
            'quantity' => 2,
            'target_mrp' => 350,
        ], $this->user->id);

        $this->assertEquals(2, $adjustment->quantity);
        $productX->refresh();
        $quickProduct->refresh();

        $this->assertEquals(3, $productX->quantity);
        $this->assertEquals(2, $quickProduct->quantity);
        $this->assertDatabaseHas('purchase_items', [
            'product_id' => $quickProduct->id,
            'received_quantity' => 2,
            'unit_price' => 150,
        ]);
    }

    public function test_reallocation_rejects_exceeding_source_batch_quantity(): void
    {
        $service = app(InventoryAdjustmentService::class);

        $productX = Product::factory()->create(['quantity' => 4]);
        InventoryStock::create(['product_id' => $productX->id, 'quantity' => 4]);

        $productY = Product::factory()->create(['quantity' => 0]);
        InventoryStock::create(['product_id' => $productY->id, 'quantity' => 0]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-EXCEED-01',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'location' => 'shop',
            'purchase_date' => now()->toDateString(),
            'total' => 400,
            'created_by' => $this->user->id,
        ]);

        $batchX = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productX->id,
            'quantity' => 4,
            'received_quantity' => 4,
            'unit_price' => 100,
            'selling_price' => 200,
            'batch_number' => 'BATCH-EX-1',
            'subtotal' => 400,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient batch stock');

        $service->reallocateStock([
            'source_product_id' => $productX->id,
            'source_purchase_item_id' => $batchX->id,
            'target_product_id' => $productY->id,
            'quantity' => 5, // More than 4
        ], $this->user->id);
    }

    public function test_quantity_decrease_adjustment_for_damaged_goods(): void
    {
        $service = app(InventoryAdjustmentService::class);

        $product = Product::factory()->create(['quantity' => 5]);
        InventoryStock::create(['product_id' => $product->id, 'quantity' => 5]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-DMG-01',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'location' => 'shop',
            'purchase_date' => now()->toDateString(),
            'total' => 500,
            'created_by' => $this->user->id,
        ]);

        $batch = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'received_quantity' => 5,
            'unit_price' => 100,
            'selling_price' => 200,
            'batch_number' => 'BATCH-DMG-1',
            'subtotal' => 500,
        ]);

        $adj = $service->adjustQuantity([
            'product_id' => $product->id,
            'purchase_item_id' => $batch->id,
            'type' => 'quantity_decrease',
            'quantity' => 2,
            'reason' => 'damaged',
            'notes' => 'Damaged in transit',
        ], $this->user->id);

        $this->assertEquals('quantity_decrease', $adj->type);
        $this->assertEquals(2, $adj->quantity);

        $product->refresh();
        $batch->refresh();
        $this->assertEquals(3, $product->quantity);
        $this->assertEquals(3, $batch->received_quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment_decrease',
            'quantity' => -2,
            'balance_after' => 3,
        ]);
    }

    public function test_livewire_stock_adjustment_modal_component(): void
    {
        $productX = Product::factory()->create(['name' => 'Modal X', 'quantity' => 10]);
        InventoryStock::create(['product_id' => $productX->id, 'quantity' => 10]);

        $productY = Product::factory()->create(['name' => 'Modal Y', 'quantity' => 0]);
        InventoryStock::create(['product_id' => $productY->id, 'quantity' => 0]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-MODAL-01',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'location' => 'shop',
            'purchase_date' => now()->toDateString(),
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);

        $batch = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productX->id,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 100,
            'selling_price' => 200,
            'batch_number' => 'BATCH-MODAL-1',
            'subtotal' => 1000,
        ]);

        $this->actingAs($this->user);

        Livewire::test(StockAdjustmentModal::class)
            ->dispatch('open-adjustment-modal', productId: $productX->id, batchId: $batch->id)
            ->assertSet('isOpen', true)
            ->assertSet('source_product_id', $productX->id)
            ->assertSet('source_purchase_item_id', $batch->id)
            ->set('target_product_id', $productY->id)
            ->set('quantity', 4)
            ->set('reason', 'misallocation_split')
            ->call('submitAdjustment')
            ->assertHasNoErrors()
            ->assertSet('isOpen', false)
            ->assertDispatched('toast')
            ->assertDispatched('inventory-adjusted');

        $productX->refresh();
        $productY->refresh();

        $this->assertEquals(6, $productX->quantity);
        $this->assertEquals(4, $productY->quantity);
    }

    public function test_livewire_modal_can_toggle_quick_product_and_create_product(): void
    {
        $productX = Product::factory()->create(['name' => 'Modal X2', 'quantity' => 10]);
        InventoryStock::create(['product_id' => $productX->id, 'quantity' => 10]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-MODAL-02',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'location' => 'shop',
            'purchase_date' => now()->toDateString(),
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);

        $batch = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productX->id,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 100,
            'selling_price' => 200,
            'batch_number' => 'BATCH-MODAL-2',
            'subtotal' => 1000,
        ]);

        $this->actingAs($this->user);

        Livewire::test(StockAdjustmentModal::class)
            ->dispatch('open-adjustment-modal', productId: $productX->id, batchId: $batch->id)
            ->assertSet('isOpen', true)
            ->call('toggleQuickProduct')
            ->assertSet('showQuickProduct', true)
            ->assertSee('Quick Product Master Entry')
            ->assertSee($this->category->name)
            ->set('quick_name', 'Quick Lip Tint')
            ->set('quick_mrp', 299)
            ->set('quick_category_id', $this->category->id)
            ->set('quick_unit_id', $this->unit->id)
            ->call('saveQuickProduct')
            ->assertHasNoErrors()
            ->assertSet('showQuickProduct', false)
            ->set('quantity', 3)
            ->call('submitAdjustment')
            ->assertHasNoErrors()
            ->assertSet('isOpen', false)
            ->assertDispatched('toast')
            ->assertDispatched('inventory-adjusted');

        $productX->refresh();
        $this->assertEquals(7, $productX->quantity);

        $newProduct = Product::where('name', 'Quick Lip Tint')->first();
        $this->assertNotNull($newProduct);
        $this->assertEquals(3, $newProduct->quantity);
    }

    public function test_modal_renders_barcode_search_for_source_product_and_ajax_search_finds_by_barcode(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sku' => 'SKU-BARCODE-01',
            'product_code' => '8901234567890',
            'name' => 'Barcode Test Serum',
            'mrp' => 899,
            'selling_price' => 850,
            'quantity' => 15,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $product->id, 'quantity' => 15]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-BC-01',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'location' => 'shop',
            'purchase_date' => now()->toDateString(),
            'total' => 1500,
            'created_by' => $this->user->id,
        ]);

        $batch = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'received_quantity' => 15,
            'unit_price' => 100,
            'selling_price' => 850,
            'batch_number' => 'BATCH-BC-01',
            'subtotal' => 1500,
        ]);

        $this->actingAs($this->user);

        // Verify AJAX product search by barcode returns exact item with all_stock
        $response = $this->postJson(route('ajax.products.search'), [
            'q' => '8901234567890',
            'all_stock' => true,
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $product->id,
            'product_code' => '8901234567890',
            'sku' => 'SKU-BARCODE-01',
            'name' => 'Barcode Test Serum',
        ]);

        // Verify Livewire modal renders the TomSelect barcode search component and correctly binds selection
        Livewire::test(StockAdjustmentModal::class)
            ->dispatch('open-adjustment-modal')
            ->assertSet('isOpen', true)
            ->assertSet('source_product_id', null)
            ->assertSee('Search product name, barcode or SKU...')
            ->assertSee('source_product_select')
            ->assertSee('handleBarcodeScan')
            ->set('source_product_id', $product->id)
            ->assertSet('source_purchase_item_id', $batch->id)
            ->assertSee('BATCH-BC-01');
    }
}
