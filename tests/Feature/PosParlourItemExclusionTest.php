<?php

namespace Tests\Feature;

use App\DTOs\SaleData;
use App\DTOs\SaleItemData;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseLocation;
use App\Exceptions\SaleException;
use App\Livewire\Products\ProductForm;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosParlourItemExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $salesUser;

    protected Category $category;

    protected Unit $unit;

    protected Supplier $supplier;

    protected Purchase $purchaseShop;

    protected Purchase $purchaseParlour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->salesUser = User::factory()->create(['role' => 'sales']);

        $this->category = Category::create([
            'name' => 'Hair Care',
            'slug' => 'hair-care',
        ]);

        $this->unit = Unit::create([
            'name' => 'Bottle',
            'symbol' => 'btl',
        ]);

        $this->supplier = Supplier::factory()->create();

        $this->purchaseShop = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PO-SHOP-001',
            'purchase_date' => now()->subDays(2),
            'created_by' => $this->admin->id,
            'total' => 5000,
            'location' => PurchaseLocation::SHOP,
        ]);

        $this->purchaseParlour = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PO-PARLOUR-001',
            'purchase_date' => now()->subDays(1),
            'created_by' => $this->admin->id,
            'total' => 5000,
            'location' => PurchaseLocation::PARLOUR,
        ]);
    }

    public function test_product_tagged_as_parlour_is_excluded_from_pos_search(): void
    {
        // 1. Pure parlour product
        $parlourProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Backbar Shampoo 5L',
            'product_code' => 'PARLOUR-SHMP',
            'location' => PurchaseLocation::PARLOUR,
            'selling_price' => 500,
            'quantity' => 10,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $parlourProduct->id, 'quantity' => 10]);

        // 2. Pure shop product
        $shopProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Retail Shampoo 250ml',
            'product_code' => 'RETAIL-SHMP',
            'location' => PurchaseLocation::SHOP,
            'selling_price' => 250,
            'quantity' => 10,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $shopProduct->id, 'quantity' => 10]);

        // POS Search (regular search without for_purchase or include_parlour)
        $response = $this->actingAs($this->salesUser)->postJson(route('ajax.products.search'), [
            'q' => 'Shampoo',
        ]);

        $response->assertOk();
        $results = collect($response->json());

        // Parlour product MUST NOT be returned
        $this->assertFalse($results->contains('id', $parlourProduct->id));

        // Shop product MUST be returned
        $this->assertTrue($results->contains('id', $shopProduct->id));
    }

    public function test_product_with_only_parlour_batches_is_excluded_from_pos_search(): void
    {
        // Product default location is shop, but batches are ONLY received into parlour
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Facial Bleach Tub',
            'product_code' => 'BLEACH-01',
            'location' => PurchaseLocation::SHOP,
            'selling_price' => 800,
            'quantity' => 8,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $product->id, 'quantity' => 8]);

        PurchaseItem::create([
            'purchase_id' => $this->purchaseParlour->id,
            'product_id' => $product->id,
            'batch_number' => 'BATCH-PARLOUR-1',
            'location' => 'parlour',
            'quantity' => 8,
            'received_quantity' => 8,
            'unit_price' => 400,
            'subtotal' => 3200,
        ]);

        // POS Search
        $response = $this->actingAs($this->salesUser)->postJson(route('ajax.products.search'), [
            'q' => 'Bleach',
        ]);

        $response->assertOk();
        $results = collect($response->json());

        // Since all active stock is in parlour, item must NOT be available at POS
        $this->assertFalse($results->contains('id', $product->id));
    }

    public function test_dual_location_product_reflects_only_shop_stock_at_pos(): void
    {
        // 5 units in shop, 15 units in parlour = 20 total
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Hydrating Face Cream',
            'product_code' => 'CREAM-HYD',
            'location' => PurchaseLocation::SHOP,
            'selling_price' => 450,
            'quantity' => 20,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $product->id, 'quantity' => 20]);

        // Shop batch: 5 units
        PurchaseItem::create([
            'purchase_id' => $this->purchaseShop->id,
            'product_id' => $product->id,
            'batch_number' => 'BATCH-SHOP-1',
            'location' => 'shop',
            'quantity' => 5,
            'received_quantity' => 5,
            'unit_price' => 200,
            'subtotal' => 1000,
        ]);

        // Parlour batch: 15 units
        PurchaseItem::create([
            'purchase_id' => $this->purchaseParlour->id,
            'product_id' => $product->id,
            'batch_number' => 'BATCH-PARLOUR-2',
            'location' => 'parlour',
            'quantity' => 15,
            'received_quantity' => 15,
            'unit_price' => 200,
            'subtotal' => 3000,
        ]);

        // POS Search
        $response = $this->actingAs($this->salesUser)->postJson(route('ajax.products.search'), [
            'q' => 'Cream',
        ]);

        $response->assertOk();
        $results = collect($response->json());

        $this->assertTrue($results->contains('id', $product->id));
        $item = $results->firstWhere('id', $product->id);

        // Crucial: The available POS quantity must be 5 (shop stock), NOT 20 (total stock)
        $this->assertEquals(5, $item['quantity']);
    }

    public function test_purchase_order_and_adjustment_can_search_parlour_products(): void
    {
        $parlourProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Salon Conditioner 5L',
            'product_code' => 'PARLOUR-COND',
            'location' => PurchaseLocation::PARLOUR,
            'selling_price' => 700,
            'quantity' => 5,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $parlourProduct->id, 'quantity' => 5]);

        // 1. Search for PO (for_purchase = 1)
        $poResponse = $this->actingAs($this->admin)->postJson(route('ajax.products.search'), [
            'q' => 'Conditioner',
            'for_purchase' => 1,
        ]);
        $poResponse->assertOk();
        $this->assertTrue(collect($poResponse->json())->contains('id', $parlourProduct->id));

        // 2. Search for Stock Adjustment (include_parlour = 1, all_stock = 1)
        $adjResponse = $this->actingAs($this->admin)->postJson(route('ajax.products.search'), [
            'q' => 'Conditioner',
            'include_parlour' => 1,
            'all_stock' => 1,
        ]);
        $adjResponse->assertOk();
        $this->assertTrue(collect($adjResponse->json())->contains('id', $parlourProduct->id));
    }

    public function test_sale_creation_rejects_parlour_tagged_product(): void
    {
        $parlourProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Internal Bleach Powder',
            'product_code' => 'INT-BLEACH',
            'location' => PurchaseLocation::PARLOUR,
            'selling_price' => 300,
            'quantity' => 10,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $parlourProduct->id, 'quantity' => 10]);

        $saleService = app(SaleService::class);

        $saleData = new SaleData(
            sale_date: now(),
            payment_method: PaymentMethod::CASH,
            created_by: $this->admin->id,
            items: [
                new SaleItemData(
                    product_id: $parlourProduct->id,
                    service_id: null,
                    quantity: 1,
                    unit_price: 300,
                    discount: 0
                ),
            ],
            notes: 'Test sale',
            cash_received: 300,
        );

        $this->expectException(SaleException::class);
        $saleService->createSale($saleData);
    }

    public function test_sale_creation_only_deducts_from_shop_batches(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Salon Serum',
            'product_code' => 'SERUM-SHOP-PARLOUR',
            'location' => PurchaseLocation::SHOP,
            'selling_price' => 150,
            'quantity' => 10,
            'is_active' => true,
        ]);
        InventoryStock::create(['product_id' => $product->id, 'quantity' => 10]);

        // Older batch in Parlour: 6 units (received 3 days ago)
        $parlourBatch = PurchaseItem::create([
            'purchase_id' => $this->purchaseParlour->id,
            'product_id' => $product->id,
            'batch_number' => 'PARLOUR-BATCH-OLD',
            'location' => 'parlour',
            'quantity' => 6,
            'received_quantity' => 6,
            'unit_price' => 70,
            'subtotal' => 420,
        ]);

        // Newer batch in Shop: 4 units (received 1 day ago)
        $shopBatch = PurchaseItem::create([
            'purchase_id' => $this->purchaseShop->id,
            'product_id' => $product->id,
            'batch_number' => 'SHOP-BATCH-NEW',
            'location' => 'shop',
            'quantity' => 4,
            'received_quantity' => 4,
            'unit_price' => 75,
            'subtotal' => 300,
        ]);

        $saleService = app(SaleService::class);

        // Sell 2 units at POS
        $saleData = new SaleData(
            sale_date: now(),
            payment_method: PaymentMethod::CASH,
            created_by: $this->admin->id,
            items: [
                new SaleItemData(
                    product_id: $product->id,
                    service_id: null,
                    quantity: 2,
                    unit_price: 150,
                    discount: 0
                ),
            ],
            notes: 'Test shop sale',
            cash_received: 300,
        );

        $sale = $saleService->createSale($saleData);

        $parlourBatch->refresh();
        $shopBatch->refresh();

        // Crucial: Parlour batch MUST remain completely untouched (still 6)
        $this->assertEquals(6, $parlourBatch->received_quantity);

        // Shop batch must have deducted the 2 units (4 - 2 = 2)
        $this->assertEquals(2, $shopBatch->received_quantity);
    }

    public function test_product_form_allows_selecting_parlour_location(): void
    {
        $company = Company::create([
            'company_code' => 'COMP-LOR',
            'company_name' => 'L\'Oreal Paris',
            'short_name' => 'L\'Oreal',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProductForm::class)
            ->call('create')
            ->set('name', 'Parlour Special Treatment')
            ->set('sku', 'PARLOUR-SPEC-01')
            ->set('category_id', $this->category->id)
            ->set('unit_id', $this->unit->id)
            ->set('company_id', $company->id)
            ->set('mrp', 500)
            ->set('min_stock', 5)
            ->set('location', 'parlour')
            ->call('save')
            ->assertHasNoErrors();

        $created = Product::where('sku', 'PARLOUR-SPEC-01')->first();
        $this->assertNotNull($created);
        $this->assertEquals(PurchaseLocation::PARLOUR, $created->location);
    }
}
