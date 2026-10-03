<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Category;
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
