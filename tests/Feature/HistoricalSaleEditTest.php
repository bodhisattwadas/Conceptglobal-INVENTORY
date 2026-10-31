<?php

namespace Tests\Feature;

use App\DTOs\SaleData;
use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\DiscountCoupon;
use App\Models\FinanceTransaction;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalSaleEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $salesUser;

    protected User $manager;

    protected Category $category;

    protected Unit $unit;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->salesUser = User::factory()->create(['role' => 'sales']);
        $this->manager = User::factory()->create(['role' => 'manager']);

        $this->category = Category::create([
            'name' => 'General',
            'slug' => 'general',
        ]);

        $this->unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Rose Serum',
            'product_code' => 'RS-001',
            'mrp' => 200,
            'purchase_price' => 100,
            'selling_price' => 200,
            'quantity' => 20,
            'is_active' => true,
        ]);

        InventoryStock::create([
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);

        $supplier = Supplier::factory()->create();
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->subDays(5),
            'created_by' => $this->admin->id,
            'total' => 2000,
        ]);

        $purchase->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 20,
            'received_quantity' => 20,
            'unit_price' => 100,
            'selling_price' => 200,
            'subtotal' => 2000,
        ]);
    }

    protected function createTestSale(int $qty = 5, PaymentMethod $method = PaymentMethod::CASH): Sale
    {
        $saleService = app(SaleService::class);
        $total = 200 * $qty;

        $saleData = SaleData::fromArray([
            'sale_date' => now()->subDay()->format('Y-m-d'),
            'payment_method' => $method->value,
            'status' => SaleStatus::COMPLETED->value,
            'created_by' => $this->admin->id,
            'cash_received' => $method === PaymentMethod::CASH ? $total + 50 : $total,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => $qty,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        return $saleService->createSale($saleData);
    }

    public function test_admin_can_access_historical_sale_edit_page(): void
    {
        $sale = $this->createTestSale(2);

        $response = $this->actingAs($this->admin)->get(route('sales.edit', $sale));

        $response->assertOk();
        $response->assertSee($sale->invoice_number);
        $response->assertSee('Rose Serum');
    }

    public function test_sales_user_cannot_access_historical_sale_edit_page(): void
    {
        $sale = $this->createTestSale(2);

        $response = $this->actingAs($this->salesUser)->get(route('sales.edit', $sale));

        $response->assertForbidden();
    }

    public function test_manager_cannot_access_historical_sale_edit_page(): void
    {
        $sale = $this->createTestSale(2);

        $response = $this->actingAs($this->manager)->get(route('sales.edit', $sale));

        $response->assertForbidden();
    }

    public function test_admin_can_update_payment_method_from_cash_to_upi(): void
    {
        $sale = $this->createTestSale(3, PaymentMethod::CASH);
        $this->assertEquals(PaymentMethod::CASH, $sale->payment_method);

        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::UPI->value,
            'reason' => 'Customer paid via Google Pay UPI instead of cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $sale->refresh();

        $this->assertEquals(PaymentMethod::UPI, $sale->payment_method);
        $this->assertEquals(600, (float) $sale->total);

        // Check Finance Transaction
        $finance = FinanceTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->first();
        $this->assertNotNull($finance);
        $this->assertEquals(600, (float) $finance->amount);

        // Check Audit Log
        $audit = AuditLog::where('entity_type', Sale::class)
            ->where('entity_id', $sale->id)
            ->where('action', 'historical_edit')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals(PaymentMethod::CASH->value, $audit->old_values['payment_method']);
        $this->assertEquals(PaymentMethod::UPI->value, $audit->new_values['payment_method']);
        $this->assertEquals('Customer paid via Google Pay UPI instead of cash', $audit->new_values['reason']);
    }

    public function test_admin_can_update_payment_method_from_upi_to_cash_with_valid_tender(): void
    {
        $sale = $this->createTestSale(2, PaymentMethod::UPI);
        $this->assertEquals(PaymentMethod::UPI, $sale->payment_method);

        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 500,
            'reason' => 'Switched to cash payment',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $sale->refresh();

        $this->assertEquals(PaymentMethod::CASH, $sale->payment_method);
        $this->assertEquals(400, (float) $sale->total);
        $this->assertEquals(500, (float) $sale->cash_received);
        $this->assertEquals(100, (float) $sale->change);
    }

    public function test_admin_can_increase_quantity_and_stock_is_deducted(): void
    {
        // Initial stock is 20. Sale of 5 leaves 15.
        $sale = $this->createTestSale(5, PaymentMethod::CASH);
        $this->assertEquals(15, $this->product->fresh()->quantity);

        // Admin updates quantity to 8 (needs 3 more, so stock should become 12)
        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 1600,
            'reason' => 'Customer bought 3 more units',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 8,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $sale->refresh();

        $this->assertEquals(8, $sale->items->first()->quantity);
        $this->assertEquals(1600, (float) $sale->total);
        $this->assertEquals(12, $this->product->fresh()->quantity);

        // Finance transaction should be updated to 1600
        $finance = FinanceTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->first();
        $this->assertEquals(1600, (float) $finance->amount);
    }

    public function test_admin_can_decrease_quantity_and_stock_is_replenished(): void
    {
        // Initial stock is 20. Sale of 5 leaves 15.
        $sale = $this->createTestSale(5, PaymentMethod::CARD);
        $this->assertEquals(15, $this->product->fresh()->quantity);

        // Admin updates quantity to 2 (returns 3, stock should become 18)
        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::CARD->value,
            'reason' => 'Customer returned 3 items at counter',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $sale->refresh();

        $this->assertEquals(2, $sale->items->first()->quantity);
        $this->assertEquals(400, (float) $sale->total);
        $this->assertEquals(18, $this->product->fresh()->quantity);
    }

    public function test_admin_cannot_increase_quantity_beyond_available_stock(): void
    {
        // Stock is 20. Sale takes 18, leaving 2 in stock.
        $sale = $this->createTestSale(18, PaymentMethod::UPI);
        $this->assertEquals(2, $this->product->fresh()->quantity);

        // Trying to increase from 18 to 25 (available is 20 total)
        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::UPI->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 25,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertSessionHas('error');
    }

    public function test_admin_cannot_update_sale_with_invalid_discount(): void
    {
        $sale = $this->createTestSale(2, PaymentMethod::UPI);

        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::UPI->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 250, // exceeds unit price
                ],
            ],
        ]);

        $response->assertSessionHas('error');
    }

    public function test_admin_can_add_and_remove_items_in_historical_sale(): void
    {
        $secondProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Aloe Lotion',
            'product_code' => 'AL-002',
            'mrp' => 150,
            'purchase_price' => 80,
            'selling_price' => 150,
            'quantity' => 10,
            'is_active' => true,
        ]);

        InventoryStock::create([
            'product_id' => $secondProduct->id,
            'quantity' => 10,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => Supplier::factory()->create()->id,
            'purchase_date' => now()->subDays(2),
            'created_by' => $this->admin->id,
            'total' => 800,
        ]);

        $purchase->items()->create([
            'product_id' => $secondProduct->id,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 80,
            'selling_price' => 150,
            'subtotal' => 800,
        ]);

        // Sale has 4 units of product 1
        $sale = $this->createTestSale(4, PaymentMethod::CARD);
        $this->assertEquals(16, $this->product->fresh()->quantity);
        $this->assertEquals(10, $secondProduct->fresh()->quantity);

        // Edit: replace product 1 with product 2 (qty 3)
        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::CARD->value,
            'items' => [
                [
                    'product_id' => $secondProduct->id,
                    'quantity' => 3,
                    'unit_price' => 150,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        // Product 1 should be completely replenished (+4 back to 20)
        $this->assertEquals(20, $this->product->fresh()->quantity);

        // Product 2 should be deducted (-3 down to 7)
        $this->assertEquals(7, $secondProduct->fresh()->quantity);

        $sale->refresh();
        $this->assertEquals(1, $sale->items->count());
        $this->assertEquals($secondProduct->id, $sale->items->first()->product_id);
        $this->assertEquals(450, (float) $sale->total);
    }

    public function test_cancelled_sale_cannot_be_edited(): void
    {
        $sale = $this->createTestSale(3, PaymentMethod::CASH);
        app(SaleService::class)->cancelSale($sale, 'Cancelled test');

        $this->assertEquals(SaleStatus::CANCELLED, $sale->fresh()->status);

        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 600,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertSessionHas('error');
    }

    public function test_admin_can_update_payment_method_via_quick_endpoint(): void
    {
        $sale = $this->createTestSale(2, PaymentMethod::CASH);
        $this->assertEquals(PaymentMethod::CASH, $sale->payment_method);

        $response = $this->actingAs($this->admin)->patch(route('sales.update-payment-method', $sale), [
            'payment_method' => PaymentMethod::CARD->value,
            'reason' => 'Switched to Card payment upon customer request',
        ]);

        $response->assertRedirect();
        $sale->refresh();

        $this->assertEquals(PaymentMethod::CARD, $sale->payment_method);
        $this->assertEquals(400, (float) $sale->total);

        // Audit log created
        $audit = AuditLog::where('entity_type', Sale::class)
            ->where('entity_id', $sale->id)
            ->where('action', 'payment_method_edit')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals(PaymentMethod::CASH->value, $audit->old_values['payment_method']);
        $this->assertEquals(PaymentMethod::CARD->value, $audit->new_values['payment_method']);
    }

    public function test_sales_user_cannot_update_payment_method_via_quick_endpoint(): void
    {
        $sale = $this->createTestSale(2, PaymentMethod::CASH);

        $response = $this->actingAs($this->salesUser)->patch(route('sales.update-payment-method', $sale), [
            'payment_method' => PaymentMethod::UPI->value,
        ]);

        $response->assertForbidden();
        $this->assertEquals(PaymentMethod::CASH, $sale->fresh()->payment_method);
    }

    public function test_admin_can_update_payment_method_to_card_with_four_digits_ref(): void
    {
        $sale = $this->createTestSale(2, PaymentMethod::CASH);

        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::CARD->value,
            'payment_ref' => '4321',
            'reason' => 'Customer paid with Visa ending in 4321',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $sale->refresh();

        $this->assertEquals(PaymentMethod::CARD, $sale->payment_method);
        $this->assertStringContainsString('Ref: 4321', $sale->notes);

        $audit = AuditLog::where('entity_type', Sale::class)
            ->where('entity_id', $sale->id)
            ->where('action', 'historical_edit')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals('4321', $audit->new_values['payment_ref']);
    }

    public function test_admin_can_update_payment_method_to_upi_with_utr_ref(): void
    {
        $sale = $this->createTestSale(2, PaymentMethod::CASH);

        $response = $this->actingAs($this->admin)->patch(route('sales.update-payment-method', $sale), [
            'payment_method' => PaymentMethod::UPI->value,
            'payment_ref' => 'UPI9876543210',
            'reason' => 'Customer sent payment via GPay UTR',
        ]);

        $response->assertRedirect();
        $sale->refresh();

        $this->assertEquals(PaymentMethod::UPI, $sale->payment_method);
        $this->assertStringContainsString('Ref: UPI9876543210', $sale->notes);

        $audit = AuditLog::where('entity_type', Sale::class)
            ->where('entity_id', $sale->id)
            ->where('action', 'payment_method_edit')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals('UPI9876543210', $audit->new_values['payment_ref']);
    }

    public function test_edit_view_renders_card_four_digits_and_upi_utr_placeholders(): void
    {
        $sale = $this->createTestSale(2, PaymentMethod::CARD);

        $response = $this->actingAs($this->admin)->get(route('sales.edit', $sale));

        $response->assertOk();
        $response->assertSee('Card 4 Digits / Auth Ref (Optional)...');
        $response->assertSee('UPI UTR / Reference ID (Optional)...');
        $response->assertSee('Collect via QR Code / UPI App');
        $response->assertSee('Swipe / Tap on POS machine');
    }

    public function test_edit_view_renders_add_product_and_add_service_buttons(): void
    {
        $sale = $this->createTestSale(2, PaymentMethod::CASH);

        $response = $this->actingAs($this->admin)->get(route('sales.edit', $sale));

        $response->assertOk();
        $response->assertSee('Add Product');
        $response->assertSee('Add Service');
        $response->assertSee('sale_item_search_input');
    }

    public function test_ajax_product_and_service_search_accepts_query_and_all_stock(): void
    {
        $service = Service::create([
            'name' => 'Signature Facial Treatment',
            'code' => 'SRV-FACIAL',
            'cost' => 1200,
            'is_active' => true,
        ]);

        // Product search with 'query' key and 'all_stock'
        $responseP = $this->actingAs($this->admin)->postJson(route('ajax.products.search'), [
            'query' => 'Rose Serum',
            'all_stock' => 1,
        ]);
        $responseP->assertOk();
        $this->assertNotEmpty($responseP->json());
        $this->assertEquals('Rose Serum', $responseP->json()[0]['name']);

        // Service search with 'query' key
        $responseS = $this->actingAs($this->admin)->postJson(route('ajax.services.search'), [
            'query' => 'Signature Facial',
        ]);
        $responseS->assertOk();
        $this->assertNotEmpty($responseS->json());
        $this->assertEquals('Signature Facial Treatment', $responseS->json()[0]['name']);
    }

    public function test_admin_can_add_service_to_historical_sale(): void
    {
        $service = Service::create([
            'name' => 'Hair Styling Spa',
            'code' => 'SRV-HAIR',
            'cost' => 800,
            'is_active' => true,
        ]);

        $sale = $this->createTestSale(2, PaymentMethod::UPI);

        // Edit sale: keep 2 products, add 1 service
        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::UPI->value,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
                [
                    'service_id' => $service->id,
                    'quantity' => 1,
                    'unit_price' => 800,
                    'discount' => 50,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $sale->refresh();

        $this->assertCount(2, $sale->items);
        $serviceItem = $sale->items->where('service_id', $service->id)->first();
        $this->assertNotNull($serviceItem);
        $this->assertEquals(750, (float) $serviceItem->subtotal);
        $this->assertEquals(1150, (float) $sale->total); // 400 + 750
    }

    public function test_edit_view_renders_coupon_section_and_applied_coupon(): void
    {
        $coupon = DiscountCoupon::create([
            'code' => 'SAVE50',
            'type' => CouponType::Fixed,
            'value' => 50,
            'is_active' => true,
        ]);

        $sale = $this->createTestSale(2, PaymentMethod::CASH);
        $sale->update([
            'discount_coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'coupon_discount' => 50,
            'total' => 350,
        ]);

        $response = $this->actingAs($this->admin)->get(route('sales.edit', $sale));

        $response->assertOk();
        $response->assertSee('Discount Coupon');
        $response->assertSee('SAVE50');
        $response->assertSee('name="coupon_id"', false);
        $response->assertSee('name="coupon_code"', false);
    }

    public function test_admin_can_update_sale_to_apply_coupon(): void
    {
        $coupon = DiscountCoupon::create([
            'code' => 'FLAT100',
            'type' => CouponType::Fixed,
            'value' => 100,
            'is_active' => true,
            'used_count' => 0,
        ]);

        $sale = $this->createTestSale(3, PaymentMethod::UPI); // Subtotal: 600

        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::UPI->value,
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $sale->refresh();

        $this->assertEquals($coupon->id, $sale->discount_coupon_id);
        $this->assertEquals('FLAT100', $sale->coupon_code);
        $this->assertEquals(100.00, (float) $sale->coupon_discount);
        $this->assertEquals(500.00, (float) $sale->total); // 600 - 100
        $this->assertEquals(1, $coupon->fresh()->used_count);
    }

    public function test_admin_can_update_sale_to_remove_coupon(): void
    {
        $coupon = DiscountCoupon::create([
            'code' => 'SAVE40',
            'type' => CouponType::Fixed,
            'value' => 40,
            'is_active' => true,
            'used_count' => 1,
        ]);

        $sale = $this->createTestSale(2, PaymentMethod::CASH);
        $sale->update([
            'discount_coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'coupon_discount' => 40,
            'total' => 360,
        ]);

        // Submit update without coupon
        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 400,
            'coupon_id' => '',
            'coupon_code' => '',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $sale->refresh();

        $this->assertNull($sale->discount_coupon_id);
        $this->assertNull($sale->coupon_code);
        $this->assertEquals(0.00, (float) $sale->coupon_discount);
        $this->assertEquals(400.00, (float) $sale->total);
        $this->assertEquals(0, $coupon->fresh()->used_count);
    }

    public function test_admin_can_change_applied_coupon_to_another_coupon(): void
    {
        $couponA = DiscountCoupon::create([
            'code' => 'COUPONA',
            'type' => CouponType::Fixed,
            'value' => 50,
            'is_active' => true,
            'used_count' => 1,
        ]);

        $couponB = DiscountCoupon::create([
            'code' => 'COUPONB',
            'type' => CouponType::Fixed,
            'value' => 100,
            'is_active' => true,
            'used_count' => 0,
        ]);

        $sale = $this->createTestSale(2, PaymentMethod::UPI); // Subtotal: 400
        $sale->update([
            'discount_coupon_id' => $couponA->id,
            'coupon_code' => $couponA->code,
            'coupon_discount' => 50,
            'total' => 350,
        ]);

        // Change coupon from A to B
        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'payment_method' => PaymentMethod::UPI->value,
            'coupon_id' => $couponB->id,
            'coupon_code' => $couponB->code,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $sale->refresh();

        $this->assertEquals($couponB->id, $sale->discount_coupon_id);
        $this->assertEquals('COUPONB', $sale->coupon_code);
        $this->assertEquals(100.00, (float) $sale->coupon_discount);
        $this->assertEquals(300.00, (float) $sale->total); // 400 - 100
        $this->assertEquals(0, $couponA->fresh()->used_count);
        $this->assertEquals(1, $couponB->fresh()->used_count);
    }
}
