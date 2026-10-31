<?php

namespace Tests\Feature;

use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Livewire\Inventory\InventoryTable;
use App\Livewire\Inventory\StockAdjustmentModal;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use ReflectionClass;
use Tests\TestCase;

class InventoryLocationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Company $company;

    private Category $category;

    private Unit $unit;

    private Purchase $defaultPurchase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->supplier = Supplier::create([
            'name' => 'Apex Glow Suppliers',
            'contact_person' => 'Supplier Contact',
            'email' => 'apex@example.com',
            'phone' => '9876543210',
        ]);

        $this->company = Company::create([
            'company_code' => 'APEX',
            'company_name' => 'Apex Cosmetics',
        ]);

        $this->category = Category::create([
            'name' => 'Salon Essentials',
            'slug' => 'salon-essentials',
        ]);

        $this->unit = Unit::create([
            'name' => 'Bottle',
            'symbol' => 'btl',
        ]);

        $this->defaultPurchase = Purchase::create([
            'invoice_number' => 'PO-DEFAULT-01',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'location' => PurchaseLocation::SHOP,
            'purchase_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $this->admin->id,
            'total' => 5000,
        ]);
    }

    public function test_po_receipt_assigns_location_to_purchase_items(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'quantity' => 0,
        ]);

        // 1. PO for Shop
        $purchaseShop = Purchase::create([
            'invoice_number' => 'PO-SHOP-01',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'location' => PurchaseLocation::SHOP,
            'purchase_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => PurchaseStatus::ORDERED,
            'created_by' => $this->admin->id,
            'total' => 1000,
        ]);

        $itemShop = PurchaseItem::create([
            'purchase_id' => $purchaseShop->id,
            'product_id' => $product->id,
            'location' => PurchaseLocation::SHOP,
            'quantity' => 10,
            'received_quantity' => 0,
            'unit_price' => 100,
            'subtotal' => 1000,
            'batch_number' => 'BATCH-SH-01',
        ]);

        // Receive PO
        app(PurchaseService::class)->markAsReceived($purchaseShop, [
            $itemShop->id => 10,
        ], [
            $itemShop->id => 'BATCH-SH-01',
        ]);

        $itemShop->refresh();
        $this->assertEquals(PurchaseLocation::SHOP, $itemShop->location);
        $this->assertTrue($itemShop->isShop());

        // 2. PO for Parlour
        $purchaseParlour = Purchase::create([
            'invoice_number' => 'PO-PARLOUR-01',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'location' => PurchaseLocation::PARLOUR,
            'purchase_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => PurchaseStatus::ORDERED,
            'created_by' => $this->admin->id,
            'total' => 500,
        ]);

        $itemParlour = PurchaseItem::create([
            'purchase_id' => $purchaseParlour->id,
            'product_id' => $product->id,
            'location' => PurchaseLocation::PARLOUR,
            'quantity' => 5,
            'received_quantity' => 0,
            'unit_price' => 100,
            'subtotal' => 500,
            'batch_number' => 'BATCH-PL-01',
        ]);

        app(PurchaseService::class)->markAsReceived($purchaseParlour, [
            $itemParlour->id => 5,
        ], [
            $itemParlour->id => 'BATCH-PL-01',
        ]);

        $itemParlour->refresh();
        $this->assertEquals(PurchaseLocation::PARLOUR, $itemParlour->location);
        $this->assertTrue($itemParlour->isParlour());
    }

    public function test_inventory_table_renders_both_badges_when_item_is_for_both_shop_and_parlour(): void
    {
        // Product 1: Shop only
        $productShop = Product::factory()->create([
            'name' => 'Retail Shampoo',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'quantity' => 10,
        ]);
        $stockShop = InventoryStock::create([
            'product_id' => $productShop->id,
            'quantity' => 10,
        ]);
        PurchaseItem::create([
            'purchase_id' => $this->defaultPurchase->id,
            'product_id' => $productShop->id,
            'location' => PurchaseLocation::SHOP,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 50,
            'subtotal' => 500,
            'batch_number' => 'B-SHOP',
        ]);

        // Product 2: Parlour only
        $productParlour = Product::factory()->create([
            'name' => 'Backbar Conditioner',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'quantity' => 8,
        ]);
        $stockParlour = InventoryStock::create([
            'product_id' => $productParlour->id,
            'quantity' => 8,
        ]);
        PurchaseItem::create([
            'purchase_id' => $this->defaultPurchase->id,
            'product_id' => $productParlour->id,
            'location' => PurchaseLocation::PARLOUR,
            'quantity' => 8,
            'received_quantity' => 8,
            'unit_price' => 60,
            'subtotal' => 480,
            'batch_number' => 'B-PARLOUR',
        ]);

        // Product 3: BOTH Shop AND Parlour
        $productBoth = Product::factory()->create([
            'name' => 'Universal Argan Oil',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'quantity' => 15,
        ]);
        $stockBoth = InventoryStock::create([
            'product_id' => $productBoth->id,
            'quantity' => 15,
        ]);
        PurchaseItem::create([
            'purchase_id' => $this->defaultPurchase->id,
            'product_id' => $productBoth->id,
            'location' => PurchaseLocation::SHOP,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 120,
            'subtotal' => 1200,
            'batch_number' => 'B-BOTH-SH',
        ]);
        PurchaseItem::create([
            'purchase_id' => $this->defaultPurchase->id,
            'product_id' => $productBoth->id,
            'location' => PurchaseLocation::PARLOUR,
            'quantity' => 5,
            'received_quantity' => 5,
            'unit_price' => 120,
            'subtotal' => 600,
            'batch_number' => 'B-BOTH-PL',
        ]);

        $component = new InventoryTable;
        $ref = new ReflectionClass($component);

        $locationBadgesMethod = $ref->getMethod('locationBadges');
        $locationBadgesMethod->setAccessible(true);

        $locationExportMethod = $ref->getMethod('locationExport');
        $locationExportMethod->setAccessible(true);

        // Shop only product
        $htmlShop = $locationBadgesMethod->invoke($component, $stockShop);
        $this->assertStringContainsString('Shop', $htmlShop);
        $this->assertStringNotContainsString('Parlour', $htmlShop);
        $this->assertEquals('Shop', $locationExportMethod->invoke($component, $stockShop));

        // Parlour only product
        $htmlParlour = $locationBadgesMethod->invoke($component, $stockParlour);
        $this->assertStringContainsString('Parlour', $htmlParlour);
        $this->assertStringNotContainsString('Shop', $htmlParlour);
        $this->assertEquals('Parlour', $locationExportMethod->invoke($component, $stockParlour));

        // BOTH product: MUST SHOW BOTH BADGES!
        $htmlBoth = $locationBadgesMethod->invoke($component, $stockBoth);
        $this->assertStringContainsString('Shop', $htmlBoth);
        $this->assertStringContainsString('Parlour', $htmlBoth);
        $this->assertEquals('Shop, Parlour', $locationExportMethod->invoke($component, $stockBoth));

        // In batch quantities, verify location labels
        $batchQuantitiesMethod = $ref->getMethod('batchQuantities');
        $batchQuantitiesMethod->setAccessible(true);
        $htmlBatches = $batchQuantitiesMethod->invoke($component, $stockBoth);
        $this->assertStringContainsString('Shop', $htmlBatches);
        $this->assertStringContainsString('Parlour', $htmlBatches);
    }

    public function test_inventory_show_page_displays_both_location_badges_when_stocked_in_both(): void
    {
        $product = Product::factory()->create([
            'name' => 'Dual Facility Moroccan Hair Mask',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'quantity' => 14,
        ]);

        $stock = InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 14,
        ]);

        // Shop batch
        PurchaseItem::create([
            'purchase_id' => $this->defaultPurchase->id,
            'product_id' => $product->id,
            'location' => PurchaseLocation::SHOP,
            'quantity' => 8,
            'received_quantity' => 8,
            'unit_price' => 200,
            'subtotal' => 1600,
            'batch_number' => 'MASK-SH-01',
        ]);

        // Parlour batch
        PurchaseItem::create([
            'purchase_id' => $this->defaultPurchase->id,
            'product_id' => $product->id,
            'location' => PurchaseLocation::PARLOUR,
            'quantity' => 6,
            'received_quantity' => 6,
            'unit_price' => 200,
            'subtotal' => 1200,
            'batch_number' => 'MASK-PL-01',
        ]);

        $response = $this->actingAs($this->admin)->get(route('inventory.show', $stock));

        $response->assertOk();
        // Top Overview Location section has both badges
        $response->assertSee('Shop (8)');
        $response->assertSee('Parlour (6)');
        $response->assertSee('Stock present in both Shop &amp; Parlour', false);

        // Active FIFO Batches table has Location column
        $response->assertSee('Location');
        $response->assertSee('MASK-SH-01');
        $response->assertSee('MASK-PL-01');
    }

    public function test_transfer_location_moves_stock_between_shop_and_parlour_without_changing_total_stock(): void
    {
        $product = Product::factory()->create([
            'name' => 'Keratin Serum',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'quantity' => 10,
        ]);

        $stock = InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-ORIGIN-01',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'location' => PurchaseLocation::SHOP,
            'purchase_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $this->admin->id,
            'total' => 1500,
        ]);

        $sourceBatch = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'location' => PurchaseLocation::SHOP,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 150,
            'subtotal' => 1500,
            'batch_number' => 'KERATIN-B1',
        ]);

        // Transfer 4 units from Shop to Parlour
        $service = app(InventoryAdjustmentService::class);
        $adjustment = $service->transferLocation([
            'product_id' => $product->id,
            'source_purchase_item_id' => $sourceBatch->id,
            'target_location' => 'parlour',
            'quantity' => 4,
            'reason' => 'Transfer to Back Parlour for client treatments',
            'notes' => 'Internal movement',
        ], $this->admin->id);

        $this->assertNotNull($adjustment);
        $this->assertEquals('location_transfer', $adjustment->type);
        $this->assertEquals(4, $adjustment->quantity);
        $this->assertEquals('shop', $adjustment->source_location->value);
        $this->assertEquals('parlour', $adjustment->target_location->value);

        // Verify total stock has NOT changed ("NO separate calculation for now")
        $stock->refresh();
        $product->refresh();
        $this->assertEquals(10, $stock->quantity);
        $this->assertEquals(10, $product->quantity);

        // Verify batches breakdown
        $sourceBatch->refresh();
        $this->assertEquals(6, $sourceBatch->received_quantity);

        $targetBatch = PurchaseItem::where('product_id', $product->id)
            ->where('location', 'parlour')
            ->first();
        $this->assertNotNull($targetBatch);
        $this->assertEquals(4, $targetBatch->received_quantity);
        $this->assertTrue($targetBatch->isParlour());

        // Verify getStockByLocation
        $locBreakdown = $product->getStockByLocation();
        $this->assertEquals(6, $locBreakdown['shop']);
        $this->assertEquals(4, $locBreakdown['parlour']);

        // Now product has both Shop and Parlour tags!
        $component = new InventoryTable;
        $ref = new ReflectionClass($component);
        $badgesMethod = $ref->getMethod('locationBadges');
        $badgesMethod->setAccessible(true);
        $html = $badgesMethod->invoke($component, $stock);
        $this->assertStringContainsString('Shop (6)', $html);
        $this->assertStringContainsString('Parlour (4)', $html);
    }

    public function test_stock_adjustment_modal_can_move_location_via_livewire(): void
    {
        $product = Product::factory()->create([
            'name' => 'Hair Clay Wax',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'quantity' => 20,
        ]);

        InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        $batch = PurchaseItem::create([
            'purchase_id' => $this->defaultPurchase->id,
            'product_id' => $product->id,
            'location' => PurchaseLocation::PARLOUR,
            'quantity' => 20,
            'received_quantity' => 20,
            'unit_price' => 80,
            'subtotal' => 1600,
            'batch_number' => 'CLAY-P1',
        ]);

        // Use Livewire component to move 5 units from Parlour to Shop
        Livewire::actingAs($this->admin)
            ->test(StockAdjustmentModal::class)
            ->call('openModal', $product->id, $batch->id)
            ->set('adjustmentType', 'location_transfer')
            ->set('target_location', 'shop')
            ->set('quantity', 5)
            ->set('reason', 'transfer_to_shop')
            ->set('notes', 'Retail front needed stock')
            ->call('submitAdjustment')
            ->assertDispatched('toast')
            ->assertDispatched('pg:eventRefresh-inventory-table');

        $batch->refresh();
        $this->assertEquals(15, $batch->received_quantity);

        $shopBatch = PurchaseItem::where('product_id', $product->id)
            ->where('location', 'shop')
            ->first();
        $this->assertNotNull($shopBatch);
        $this->assertEquals(5, $shopBatch->received_quantity);
    }

    public function test_inventory_export_includes_location_column(): void
    {
        $product = Product::factory()->create([
            'name' => 'Tea Tree Shampoo',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'quantity' => 12,
        ]);

        $stock = InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 12,
        ]);

        // 7 units in Shop, 5 units in Parlour
        PurchaseItem::create([
            'purchase_id' => $this->defaultPurchase->id,
            'product_id' => $product->id,
            'location' => PurchaseLocation::SHOP,
            'quantity' => 7,
            'received_quantity' => 7,
            'unit_price' => 100,
            'subtotal' => 700,
            'batch_number' => 'TTS-SH-01',
        ]);
        PurchaseItem::create([
            'purchase_id' => $this->defaultPurchase->id,
            'product_id' => $product->id,
            'location' => PurchaseLocation::PARLOUR,
            'quantity' => 5,
            'received_quantity' => 5,
            'unit_price' => 100,
            'subtotal' => 500,
            'batch_number' => 'TTS-PL-01',
        ]);

        $component = new InventoryTable;
        $component->columns = $component->columns();
        $component->setUp();

        $currentHiddenStates = collect($component->columns)
            ->mapWithKeys(fn ($column) => [data_get($column, 'field') => data_get($column, 'hidden')]);

        $columnsWithHiddenState = array_map(function ($column) use ($currentHiddenStates) {
            $column->hidden = $currentHiddenStates[$column->field];

            return $column;
        }, $component->columns());

        $export = new Export;
        $prepared = $export->prepare($component->prepareToExport(), $columnsWithHiddenState, true);

        $this->assertNotEmpty($prepared['rows']);
        $row = collect($prepared['rows'])->firstWhere('SKU', $product->sku);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('Location', $row);
        $this->assertSame('Shop, Parlour', $row['Location']);

        // Assert no html tags in the exported Location
        $this->assertStringNotContainsString('<', $row['Location']);
        $this->assertStringNotContainsString('>', $row['Location']);
    }
}
