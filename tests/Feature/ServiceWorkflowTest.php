<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Livewire\Services\ServiceForm;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_services_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('services.index'));

        $response->assertOk();
        $response->assertSee('Services');
        $response->assertSee('Add Service');
    }

    public function test_sales_user_cannot_access_services_index(): void
    {
        $salesUser = User::factory()->create(['role' => 'sales']);

        $response = $this->actingAs($salesUser)->get(route('services.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_and_update_service_via_livewire(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        Livewire::test(ServiceForm::class)
            ->set('name', 'Facial Glow')
            ->set('code', 'SRV-FACIAL')
            ->set('cost', 1500)
            ->set('description', 'Deep cleansing and glow facial.')
            ->set('is_active', true)
            ->call('save')
            ->assertDispatched('close-modal', name: 'service-form-modal')
            ->assertDispatched('pg:eventRefresh-service-table');

        $this->assertDatabaseHas('services', [
            'name' => 'Facial Glow',
            'code' => 'SRV-FACIAL',
            'cost' => '1500.00',
            'is_active' => 1,
        ]);

        $service = Service::where('code', 'SRV-FACIAL')->firstOrFail();

        Livewire::test(ServiceForm::class)
            ->call('edit', $service->id)
            ->set('cost', 1800)
            ->call('save')
            ->assertDispatched('close-modal', name: 'service-form-modal')
            ->assertDispatched('pg:eventRefresh-service-table');

        $this->assertSame('1800.00', $service->fresh()->cost);
    }

    public function test_ajax_service_search_returns_active_services(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        $activeService = Service::create([
            'name' => 'Hair Brow Styling',
            'code' => 'SRV-BROW',
            'cost' => 350.00,
            'is_active' => true,
        ]);

        $inactiveService = Service::create([
            'name' => 'Old Facial Treatment',
            'code' => 'SRV-OLD',
            'cost' => 800.00,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('ajax.services.search'), [
            'q' => 'Brow',
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame($activeService->id, $data[0]['id']);
        $this->assertSame('Hair Brow Styling', $data[0]['name']);
        $this->assertSame(350.0, (float) $data[0]['price']);
        $this->assertTrue($data[0]['is_service']);

        // Inactive service should not appear in searches
        $inactiveResponse = $this->actingAs($user)->postJson(route('ajax.services.search'), [
            'q' => 'Old Facial',
        ]);
        $inactiveResponse->assertOk();
        $this->assertCount(0, $inactiveResponse->json());
    }

    public function test_sale_can_be_created_with_services_only(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        $facial = Service::create([
            'name' => 'Facial Cleansing',
            'code' => 'SRV-FC',
            'cost' => 1200.00,
            'is_active' => true,
        ]);

        $brow = Service::create([
            'name' => 'Hair Brow Trim',
            'code' => 'SRV-HBT',
            'cost' => 250.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'cash_received' => 1500,
            'items' => [
                [
                    'service_id' => $facial->id,
                    'quantity' => 1,
                    'unit_price' => 1200,
                    'discount' => 0,
                ],
                [
                    'service_id' => $brow->id,
                    'quantity' => 1,
                    'unit_price' => 250,
                    'discount' => 0,
                ],
            ],
            'round_off' => 50,
        ]);

        $response->assertCreated();
        $response->assertJson(['success' => true]);

        $sale = Sale::latest('id')->firstOrFail();
        $this->assertCount(2, $sale->items);
        $this->assertSame('1500.00', $sale->total);
        $this->assertSame('1450.00', $sale->subtotal);

        // Assert items are recorded with service_id and null product_id
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'service_id' => $facial->id,
            'product_id' => null,
            'quantity' => 1,
            'unit_price' => '1200.00',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'service_id' => $brow->id,
            'product_id' => null,
            'quantity' => 1,
            'unit_price' => '250.00',
        ]);
    }

    public function test_sale_can_be_created_with_mixed_products_and_services(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        $category = Category::create(['name' => 'Haircare', 'slug' => 'haircare']);
        $unit = Unit::create(['name' => 'Bottle', 'symbol' => 'btl']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 300,
            'selling_price' => 500,
            'quantity' => 10,
        ]);

        $this->receiveStock($product, $user, 10);

        $service = Service::create([
            'name' => 'Hair Cut & Style',
            'code' => 'SRV-HC',
            'cost' => 600.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'cash_received' => 1100,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'discount' => 0,
                ],
                [
                    'service_id' => $service->id,
                    'quantity' => 1,
                    'unit_price' => 600,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertCreated();
        $sale = Sale::latest('id')->firstOrFail();

        // Product inventory must have decremented by 1 (10 - 1 = 9)
        $this->assertSame(9, (int) $product->fresh()->quantity);

        // Now cancel the sale: product stock should be restored to 10 without issue
        $cancelResponse = $this->actingAs($user)->delete(route('sales.destroy', $sale), [
            'reason' => 'Customer request',
        ]);

        $cancelResponse->assertRedirect(route('sales.index'));
        $this->assertSame(10, (int) $product->fresh()->quantity);
        $this->assertSame(SaleStatus::CANCELLED, $sale->fresh()->status);
    }

    public function test_product_and_service_discounts_are_displayed_separately(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        // Check POS create view renders separate discount sections
        $createResponse = $this->actingAs($user)->get(route('sales.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('Product Discount');
        $createResponse->assertSee('Service Discount');
        $createResponse->assertSee('productDiscount > 0', false);
        $createResponse->assertSee('serviceDiscount > 0', false);

        $category = Category::create(['name' => 'Cosmetics', 'slug' => 'cosmetics-'.uniqid()]);
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 100,
            'selling_price' => 300,
            'quantity' => 10,
        ]);
        $this->receiveStock($product, $user, 10);

        $service = Service::create([
            'name' => 'Gold Facial Treatment',
            'code' => 'SRV-GF',
            'cost' => 1500.00,
            'is_active' => true,
        ]);

        // Product: price 300, discount 50 -> net 250
        // Service: price 1500, discount 200 -> net 1300
        // Total: 1550
        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'cash_received' => 1550,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 300,
                    'discount' => 50,
                ],
                [
                    'service_id' => $service->id,
                    'quantity' => 1,
                    'unit_price' => 1500,
                    'discount' => 200,
                ],
            ],
        ]);

        $response->assertCreated();
        $sale = Sale::latest('id')->firstOrFail();

        // Verify show view displays Product Discount and Service Discount separately
        $showResponse = $this->actingAs($user)->get(route('sales.show', $sale));
        $showResponse->assertOk();
        $showResponse->assertSee('Product Discount');
        $showResponse->assertSee('Service Discount');

        // Verify print view displays Product Discount and Service Discount separately
        $printResponse = $this->actingAs($user)->get(route('sales.print', $sale));
        $printResponse->assertOk();
        $printResponse->assertSee('Product Discount');
        $printResponse->assertSee('Service Discount');
    }

    public function test_products_are_listed_at_top_then_services_in_sale_and_invoice(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        $category = Category::create(['name' => 'Hair Products', 'slug' => 'hair-products-'.uniqid()]);
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Hair Serum Alpha',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 150,
            'selling_price' => 350,
            'quantity' => 10,
        ]);
        $this->receiveStock($product, $user, 10);

        $service = Service::create([
            'name' => 'Hair Spa Deluxe',
            'code' => 'SRV-SPA',
            'cost' => 1200.00,
            'is_active' => true,
        ]);

        // Post items intentionally with service FIRST, then product
        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'cash_received' => 1550,
            'items' => [
                [
                    'service_id' => $service->id,
                    'quantity' => 1,
                    'unit_price' => 1200,
                    'discount' => 0,
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 350,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertCreated();
        $sale = Sale::latest('id')->firstOrFail();

        // Database items must be stored with product first, then service
        $saleItems = $sale->items()->get();
        $this->assertCount(2, $saleItems);
        $this->assertNotNull($saleItems[0]->product_id);
        $this->assertNull($saleItems[0]->service_id);
        $this->assertNotNull($saleItems[1]->service_id);
        $this->assertNull($saleItems[1]->product_id);

        // Print invoice view must render product before service
        $printResponse = $this->actingAs($user)->get(route('sales.print', $sale));
        $printResponse->assertOk();
        $content = $printResponse->getContent();
        $productPos = strpos($content, 'Hair Serum Alpha');
        $servicePos = strpos($content, 'Hair Spa Deluxe');
        $this->assertNotFalse($productPos);
        $this->assertNotFalse($servicePos);
        $this->assertLessThan($servicePos, $productPos, 'Product should appear before service in print invoice.');

        // Show view must also render product before service
        $showResponse = $this->actingAs($user)->get(route('sales.show', $sale));
        $showResponse->assertOk();
        $showContent = $showResponse->getContent();
        $showProductPos = strpos($showContent, 'Hair Serum Alpha');
        $showServicePos = strpos($showContent, 'Hair Spa Deluxe');
        $this->assertNotFalse($showProductPos);
        $this->assertNotFalse($showServicePos);
        $this->assertLessThan($showServicePos, $showProductPos, 'Product should appear before service in sale show view.');
    }

    private function receiveStock(Product $product, User $user, int $quantity = 5): void
    {
        $purchase = Purchase::create([
            'supplier_id' => Supplier::factory()->create()->id,
            'purchase_date' => now(),
            'created_by' => $user->id,
            'total' => (float) $product->purchase_price * $quantity,
        ]);

        $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'received_quantity' => $quantity,
            'unit_price' => $product->purchase_price,
            'subtotal' => (float) $product->purchase_price * $quantity,
            'selling_price' => $product->selling_price,
        ]);

        $product->update(['quantity' => $quantity]);
    }
}
