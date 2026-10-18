<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FinanceTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_cash_sale_requires_payment_before_completion(): void
    {
        $user = User::factory()->create();
        $sale = $this->pendingSale($user, total: 500);

        $response = $this->actingAs($user)->patch(route('sales.complete', $sale));

        $response->assertSessionHasErrors('cash_received');
        $this->assertSame(SaleStatus::PENDING, $sale->refresh()->status);
        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_pending_cash_sale_can_be_completed_with_payment_and_records_income(): void
    {
        $user = User::factory()->create();
        $sale = $this->pendingSale($user, total: 500);

        $response = $this->actingAs($user)->patch(route('sales.complete', $sale), [
            'cash_received' => 700,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $sale->refresh();
        $this->assertSame(SaleStatus::COMPLETED, $sale->status);
        $this->assertSame('700.00', $sale->cash_received);
        $this->assertSame('200.00', $sale->change);
        $this->assertTrue(FinanceTransaction::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('amount', 500)
            ->exists());
    }

    public function test_cancelled_status_and_duplicate_products_are_rejected_when_creating_a_sale(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::CANCELLED->value,
            'cash_received' => 100,
            'items' => [
                ['product_id' => 999, 'quantity' => 1, 'unit_price' => 100],
                ['product_id' => 999, 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'items.0.product_id', 'items.1.product_id']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_accepts_decimal_formatted_whole_money_values(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);
        $unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 80,
            'selling_price' => 100,
            'quantity' => 5,
        ]);
        $this->receiveStock($product, $user);

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => '100.00',
            'global_discount' => '0.00',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '100.00',
                    'discount' => '0.00',
                ],
            ],
        ]);

        $this->assertSame(201, $response->status(), $response->getContent());
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'unit_price' => 100,
            'discount' => 0,
            'subtotal' => 100,
        ]);
    }

    public function test_sale_preserves_decimal_item_discounts_totals_and_finance_income(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Decimal Category', 'slug' => 'decimal-category']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 50.25,
            'selling_price' => 99,
            'quantity' => 5,
        ]);
        $this->receiveStock($product, $user);

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => '200.50',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => '99.00',
                'discount' => '10.40',
            ]],
        ]);

        $this->assertSame(201, $response->status(), $response->getContent());
        $response->assertJsonPath('success', true);
        $sale = Sale::findOrFail($response->json('data.id'));
        $item = $sale->items()->firstOrFail();
        $this->assertSame('10.40', $item->discount);
        $this->assertSame('88.60', $item->final_price);
        $this->assertSame('50.25', $item->cost_price);
        $this->assertSame('177.20', $item->subtotal);
        $this->assertSame('198.00', $sale->subtotal);
        $this->assertSame('20.80', $sale->total_discount);
        $this->assertSame('177.20', $sale->total);
        $this->assertSame('23.30', $sale->change);
        $this->assertSame(3, $product->refresh()->quantity);
        $this->assertSame('177.20', FinanceTransaction::where('reference_id', $sale->id)
            ->where('reference_type', Sale::class)->firstOrFail()->amount);
    }

    public function test_pending_sale_can_be_completed_with_exact_decimal_payment(): void
    {
        $user = User::factory()->create();
        $sale = $this->pendingSale($user, total: 89.60);

        $response = $this->actingAs($user)->patch(route('sales.complete', $sale), [
            'cash_received' => '89.60',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(SaleStatus::COMPLETED, $sale->refresh()->status);
        $this->assertSame('89.60', $sale->cash_received);
        $this->assertSame('0.00', $sale->change);
    }

    public function test_sale_can_be_created_with_upi_payment(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Skin', 'slug' => 'skin']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 100,
            'selling_price' => 150,
            'quantity' => 10,
        ]);
        $this->receiveStock($product, $user);

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::UPI->value,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 150],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('sales', [
            'payment_method' => 'upi',
            'status' => 'completed',
            'total' => 300,
            'cash_received' => 300,
            'change' => 0,
        ]);
    }

    public function test_sale_can_be_created_with_card_payment(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Hair', 'slug' => 'hair']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 200,
            'selling_price' => 250,
            'quantity' => 10,
        ]);
        $this->receiveStock($product, $user);

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CARD->value,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 250],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('sales', [
            'payment_method' => 'card',
            'status' => 'completed',
            'total' => 250,
            'cash_received' => 250,
            'change' => 0,
        ]);
    }

    public function test_customer_lookup_by_phone_finds_existing_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Asha Sharma',
            'phone' => '+91 98765 43210',
            'address' => '12 Park Street, Kolkata',
            'email' => 'asha@example.com',
        ]);

        // Exact match / formatted
        $response1 = $this->actingAs($user)->postJson(route('ajax.customers.by-phone'), [
            'phone' => '+91 98765 43210',
        ]);
        $response1->assertOk()
            ->assertJson([
                'found' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => 'Asha Sharma',
                    'address' => '12 Park Street, Kolkata',
                    'email' => 'asha@example.com',
                ],
            ]);

        // Unformatted raw 10 digits
        $response2 = $this->actingAs($user)->postJson(route('ajax.customers.by-phone'), [
            'phone' => '9876543210',
        ]);
        $response2->assertOk()
            ->assertJson([
                'found' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => 'Asha Sharma',
                ],
            ]);
    }

    public function test_customer_lookup_by_phone_returns_not_found_for_unregistered_phone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('ajax.customers.by-phone'), [
            'phone' => '9999900000',
        ]);

        $response->assertOk()
            ->assertJson([
                'found' => false,
                'customer' => null,
            ]);
    }

    public function test_sale_can_be_created_with_custom_sale_date(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'General', 'slug' => 'general']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 50,
            'selling_price' => 100,
            'quantity' => 5,
        ]);
        $this->receiveStock($product, $user);

        $customDate = '2026-05-15';

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => $customDate,
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'cash_received' => 100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertCreated();

        $sale = Sale::latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertSame($customDate, $sale->sale_date->toDateString());

        $transaction = FinanceTransaction::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame($customDate, $transaction->transaction_date->toDateString());
    }

    public function test_user_can_update_sale_date_and_sync_finance_transaction(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'General', 'slug' => 'general']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 50,
            'selling_price' => 100,
            'quantity' => 5,
        ]);
        $this->receiveStock($product, $user);

        // Create completed sale
        $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => '2026-05-15',
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'cash_received' => 100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $sale = Sale::latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertSame('2026-05-15', $sale->sale_date->toDateString());

        // Update date to 2026-07-20
        $newDate = '2026-07-20';
        $response = $this->actingAs($user)->patch(route('sales.update-date', $sale), [
            'sale_date' => $newDate,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $sale->refresh();
        $this->assertSame($newDate, $sale->sale_date->toDateString());

        $transaction = FinanceTransaction::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame($newDate, $transaction->transaction_date->toDateString());
    }

    public function test_sale_date_update_validation_rejects_empty_or_invalid_date(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $sale = $this->pendingSale($user, total: 200);

        $response = $this->actingAs($user)->patch(route('sales.update-date', $sale), [
            'sale_date' => '',
        ]);

        $response->assertSessionHasErrors('sale_date');

        $response2 = $this->actingAs($user)->patch(route('sales.update-date', $sale), [
            'sale_date' => 'not-a-valid-date',
        ]);

        $response2->assertSessionHasErrors('sale_date');
    }

    public function test_user_can_update_sale_date_via_json(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $sale = $this->pendingSale($user, total: 300);

        $response = $this->actingAs($user)->patchJson(route('sales.update-date', $sale), [
            'sale_date' => '2026-08-10',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Sale date updated successfully.',
                'sale_date' => '2026-08-10',
            ]);

        $this->assertSame('2026-08-10', $sale->refresh()->sale_date->toDateString());
    }

    public function test_pos_create_view_renders_exact_cash_button_and_cash_payment_controls(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        $response = $this->actingAs($user)->get(route('sales.create'));
        $response->assertOk();
        $response->assertSee('selectPaymentMethod(\'cash\')', false);
        $response->assertSee('selectExactCash()', false);
        $response->assertSee('isExactCash', false);
        $response->assertSee('EXACT');
    }

    public function test_pos_create_view_renders_row_level_product_dropdowns_and_add_product_button(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        $response = $this->actingAs($user)->get(route('sales.create'));
        $response->assertOk();
        $response->assertSee('initRowProductSelect($el, item)', false);
        $response->assertSee('addRow()', false);
        $response->assertSee(':disabled="hasBlankRow"', false);
        $response->assertSee('hasBlankRow', false);
        $response->assertSee('Add Product');
        $response->assertDontSee('x-ref="productSelect"', false);
    }

    private function receiveStock(Product $product, User $user): void
    {
        $purchase = Purchase::create([
            'supplier_id' => Supplier::factory()->create()->id,
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

    private function pendingSale(User $user, float $total): Sale
    {
        return Sale::create([
            'invoice_number' => 'INV.TEST.'.str_pad((string) (Sale::count() + 1), 4, '0', STR_PAD_LEFT),
            'created_by' => $user->id,
            'sale_date' => now(),
            'status' => SaleStatus::PENDING,
            'subtotal' => $total,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => $total,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH,
        ]);
    }
}
