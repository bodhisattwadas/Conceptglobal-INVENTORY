<?php

namespace Tests\Feature;

use App\DTOs\DiscountCouponData;
use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\DiscountCouponException;
use App\Livewire\Coupons\CouponForm;
use App\Models\Category;
use App\Models\DiscountCoupon;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\DiscountCouponService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CouponWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_coupon_form_handles_percentage_and_flat_modes_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Testing Percentage mode with cap
        Livewire::actingAs($admin)
            ->test(CouponForm::class)
            ->set('code', 'SUMMER15')
            ->set('type', 'percentage')
            ->set('value', '15')
            ->set('max_discount_amount', '300')
            ->set('min_purchase_amount', '500')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Coupon created successfully.', type: 'success');

        $coupon = DiscountCoupon::where('code', 'SUMMER15')->first();
        $this->assertNotNull($coupon);
        $this->assertSame(CouponType::Percentage, $coupon->type);
        $this->assertSame(15, $coupon->value);
        $this->assertSame(300, $coupon->max_discount_amount);

        // Testing Flat mode resets / ignores max_discount_amount
        Livewire::actingAs($admin)
            ->test(CouponForm::class)
            ->set('code', 'FLAT200')
            ->set('type', 'fixed')
            ->set('value', '200')
            ->set('max_discount_amount', '500') // should be forced to null for flat
            ->call('save')
            ->assertHasNoErrors();

        $flatCoupon = DiscountCoupon::where('code', 'FLAT200')->first();
        $this->assertNotNull($flatCoupon);
        $this->assertSame(CouponType::Fixed, $flatCoupon->type);
        $this->assertSame(200, $flatCoupon->value);
        $this->assertNull($flatCoupon->max_discount_amount);

        // Percentage above 100 fails validation
        Livewire::actingAs($admin)
            ->test(CouponForm::class)
            ->set('code', 'TOO_HIGH')
            ->set('type', 'percentage')
            ->set('value', '101')
            ->call('save')
            ->assertHasErrors(['value']);
    }

    public function test_can_create_percentage_and_flat_coupons_via_service(): void
    {
        $service = app(DiscountCouponService::class);

        // Percentage coupon
        $pctData = DiscountCouponData::fromArray([
            'code' => 'SAVE10',
            'type' => 'percentage',
            'value' => 10,
            'max_discount_amount' => 500,
            'min_purchase_amount' => 1000,
        ]);
        $pctCoupon = $service->createCoupon($pctData);

        $this->assertSame('SAVE10', $pctCoupon->code);
        $this->assertSame(CouponType::Percentage, $pctCoupon->type);
        $this->assertSame(10, $pctCoupon->value);
        $this->assertSame(500, $pctCoupon->max_discount_amount);
        $this->assertSame(1000, $pctCoupon->min_purchase_amount);

        // Flat coupon
        $flatData = DiscountCouponData::fromArray([
            'code' => 'FLAT100',
            'type' => 'fixed',
            'value' => 100,
            'min_purchase_amount' => 500,
        ]);
        $flatCoupon = $service->createCoupon($flatData);

        $this->assertSame('FLAT100', $flatCoupon->code);
        $this->assertSame(CouponType::Fixed, $flatCoupon->type);
        $this->assertSame(100, $flatCoupon->value);
        $this->assertNull($flatCoupon->max_discount_amount);
    }

    public function test_percentage_coupon_value_exceeding_100_is_rejected(): void
    {
        $this->expectException(DiscountCouponException::class);

        $service = app(DiscountCouponService::class);
        $service->createCoupon(DiscountCouponData::fromArray([
            'code' => 'INVALID150',
            'type' => 'percentage',
            'value' => 150,
        ]));
    }

    public function test_coupon_api_validates_and_calculates_discounts_at_checkout(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        $flatCoupon = DiscountCoupon::create([
            'code' => 'FLAT50',
            'type' => CouponType::Fixed,
            'value' => 50,
            'min_purchase_amount' => 200,
            'is_active' => true,
        ]);

        $pctCoupon = DiscountCoupon::create([
            'code' => 'PCT20',
            'type' => CouponType::Percentage,
            'value' => 20,
            'max_discount_amount' => 80,
            'min_purchase_amount' => 100,
            'is_active' => true,
        ]);

        // Flat discount test on ₹300 subtotal -> saves ₹50
        $response = $this->actingAs($user)->postJson(route('ajax.coupons.apply'), [
            'code' => 'flat50',
            'subtotal' => 300,
        ]);
        $response->assertOk();
        $response->assertJsonPath('valid', true);
        $response->assertJsonPath('discount_amount', 50);

        // Flat discount below min_purchase_amount (₹150 < ₹200) -> rejected
        $response = $this->actingAs($user)->postJson(route('ajax.coupons.apply'), [
            'code' => 'FLAT50',
            'subtotal' => 150,
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('valid', false);

        // Percentage discount on ₹500 subtotal (20% of 500 = 100, but capped at 80) -> saves ₹80
        $response = $this->actingAs($user)->postJson(route('ajax.coupons.apply'), [
            'code' => 'PCT20',
            'subtotal' => 500,
        ]);
        $response->assertOk();
        $response->assertJsonPath('valid', true);
        $response->assertJsonPath('discount_amount', 80);

        // Percentage discount on ₹200 subtotal (20% of 200 = 40, under 80 cap) -> saves ₹40
        $response = $this->actingAs($user)->postJson(route('ajax.coupons.apply'), [
            'code' => 'PCT20',
            'subtotal' => 200,
        ]);
        $response->assertOk();
        $response->assertJsonPath('valid', true);
        $response->assertJsonPath('discount_amount', 40);
    }

    public function test_coupon_api_rejects_inactive_or_expired_or_limit_reached_coupons(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        DiscountCoupon::create([
            'code' => 'INACTIVE',
            'type' => CouponType::Fixed,
            'value' => 50,
            'is_active' => false,
        ]);

        DiscountCoupon::create([
            'code' => 'EXPIRED',
            'type' => CouponType::Fixed,
            'value' => 50,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        DiscountCoupon::create([
            'code' => 'MAXED',
            'type' => CouponType::Fixed,
            'value' => 50,
            'usage_limit' => 2,
            'used_count' => 2,
            'is_active' => true,
        ]);

        // Inactive
        $this->actingAs($user)->postJson(route('ajax.coupons.apply'), [
            'code' => 'INACTIVE',
            'subtotal' => 500,
        ])->assertStatus(422);

        // Expired
        $this->actingAs($user)->postJson(route('ajax.coupons.apply'), [
            'code' => 'EXPIRED',
            'subtotal' => 500,
        ])->assertStatus(422);

        // Limit reached
        $this->actingAs($user)->postJson(route('ajax.coupons.apply'), [
            'code' => 'MAXED',
            'subtotal' => 500,
        ])->assertStatus(422);
    }

    public function test_sale_can_be_completed_with_coupon_and_increments_used_count(): void
    {
        $user = User::factory()->create(['role' => 'sales']);
        $product = $this->productWithStock(10, price: 100, user: $user);

        $coupon = DiscountCoupon::create([
            'code' => 'SAVE20PCT',
            'type' => CouponType::Percentage,
            'value' => 20,
            'min_purchase_amount' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $this->assertSame(0, $coupon->used_count);

        // Cart subtotal: 3 x 100 = 300. 20% discount = 60. Total bill = 240.
        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'global_discount' => 60,
            'cash_received' => 240,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 100,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertCreated();

        $sale = Sale::latest()->first();
        $this->assertSame('300.00', $sale->subtotal);
        $this->assertSame('60.00', $sale->global_discount);
        $this->assertSame('240.00', $sale->total);
        $this->assertSame($coupon->id, $sale->discount_coupon_id);
        $this->assertSame('SAVE20PCT', $sale->coupon_code);

        // Used count incremented
        $this->assertSame(1, $coupon->refresh()->used_count);

        // Cancelling sale restores used count
        $saleService = app(SaleService::class);
        $saleService->cancelSale($sale, 'Testing cancellation');
        $this->assertSame(0, $coupon->refresh()->used_count);

        // Restoring sale re-increments used count
        $saleService->restoreSale($sale);
        $this->assertSame(1, $coupon->refresh()->used_count);
    }

    public function test_service_retrieves_only_active_unexpired_available_coupons(): void
    {
        $service = app(DiscountCouponService::class);

        // 1. Valid Active Percentage Coupon
        $c1 = DiscountCoupon::create([
            'code' => 'PCT15',
            'type' => CouponType::Percentage,
            'value' => 15,
            'is_active' => true,
        ]);

        // 2. Valid Active Flat Coupon
        $c2 = DiscountCoupon::create([
            'code' => 'FLAT100',
            'type' => CouponType::Fixed,
            'value' => 100,
            'min_purchase_amount' => 500,
            'is_active' => true,
        ]);

        // 3. Inactive Coupon
        DiscountCoupon::create([
            'code' => 'INACTIVE',
            'type' => CouponType::Percentage,
            'value' => 10,
            'is_active' => false,
        ]);

        // 4. Expired Coupon
        DiscountCoupon::create([
            'code' => 'EXPIRED',
            'type' => CouponType::Percentage,
            'value' => 20,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        // 5. Future Start Date Coupon
        DiscountCoupon::create([
            'code' => 'FUTURE',
            'type' => CouponType::Percentage,
            'value' => 25,
            'starts_at' => now()->addDays(5),
            'is_active' => true,
        ]);

        // 6. Max Limit Reached Coupon
        DiscountCoupon::create([
            'code' => 'MAXED',
            'type' => CouponType::Percentage,
            'value' => 30,
            'usage_limit' => 3,
            'used_count' => 3,
            'is_active' => true,
        ]);

        $available = $service->getAvailableCoupons();

        $this->assertCount(2, $available);
        $codes = $available->pluck('code')->all();
        $this->assertContains('PCT15', $codes);
        $this->assertContains('FLAT100', $codes);
        $this->assertNotContains('INACTIVE', $codes);
        $this->assertNotContains('EXPIRED', $codes);
        $this->assertNotContains('FUTURE', $codes);
        $this->assertNotContains('MAXED', $codes);

        // Formatted label check
        $this->assertStringContainsString('FLAT100 — Flat', $c2->formatted_label);
        $this->assertStringContainsString('PCT15 — 15% OFF', $c1->formatted_label);
    }

    public function test_available_coupons_api_returns_formatted_coupons_and_applicability(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        DiscountCoupon::create([
            'code' => 'MIN1000',
            'type' => CouponType::Fixed,
            'value' => 100,
            'min_purchase_amount' => 1000,
            'is_active' => true,
        ]);

        DiscountCoupon::create([
            'code' => 'NO_MIN',
            'type' => CouponType::Percentage,
            'value' => 10,
            'is_active' => true,
        ]);

        // GET /ajax/coupons/available without subtotal
        $res = $this->actingAs($user)->getJson(route('ajax.coupons.available'));
        $res->assertOk();
        $res->assertJsonCount(2, 'coupons');

        // GET /ajax/coupons/available with subtotal 500
        $resSubtotal = $this->actingAs($user)->getJson(route('ajax.coupons.available', ['subtotal' => 500]));
        $resSubtotal->assertOk();
        $coupons = $resSubtotal->json('coupons');

        $minCoupon = collect($coupons)->firstWhere('code', 'MIN1000');
        $noMinCoupon = collect($coupons)->firstWhere('code', 'NO_MIN');

        $this->assertFalse($minCoupon['is_applicable']);
        $this->assertTrue($noMinCoupon['is_applicable']);

        // GET /ajax/coupons/available with only_applicable=1 and subtotal 500
        $resOnly = $this->actingAs($user)->getJson(route('ajax.coupons.available', [
            'subtotal' => 500,
            'only_applicable' => 1,
        ]));
        $resOnly->assertOk();
        $resOnly->assertJsonCount(1, 'coupons');
        $resOnly->assertJsonPath('coupons.0.code', 'NO_MIN');
    }

    public function test_pos_create_view_receives_and_renders_available_coupons_dropdown(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        DiscountCoupon::create([
            'code' => 'POSDROPDOWN',
            'type' => CouponType::Percentage,
            'value' => 15,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('sales.create'));
        $response->assertOk();
        $response->assertViewHas('availableCoupons');
        $response->assertSee('POSDROPDOWN');
        $response->assertSee('Select Coupon');
        $response->assertSee('Enter promo code manually');
    }

    private function productWithStock(int $quantity, float $price = 100, ?User $user = null): Product
    {
        $supplier = Supplier::create([
            'name' => 'Test Supplier '.uniqid(),
            'contact_person' => 'Contact Person',
            'email' => uniqid().'@supplier.com',
            'phone' => '9876543210',
        ]);
        $category = Category::create([
            'name' => 'Test Category '.uniqid(),
            'slug' => 'test-category-'.uniqid(),
        ]);
        $unit = Unit::create([
            'name' => 'Piece '.uniqid(),
            'symbol' => 'pc',
        ]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Test Product '.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 50,
            'selling_price' => $price,
            'quantity' => $quantity,
        ]);

        if ($user) {
            $this->receiveStock($product, $user, $supplier);
        }

        return $product;
    }

    private function receiveStock(Product $product, User $user, Supplier $supplier): void
    {
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'created_by' => $user->id,
            'total' => $product->purchase_price * $product->quantity,
        ]);

        $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => $product->quantity,
            'received_quantity' => $product->quantity,
            'unit_price' => $product->purchase_price,
            'subtotal' => $product->purchase_price * $product->quantity,
            'selling_price' => $product->selling_price,
        ]);
    }
}
