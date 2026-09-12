<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\FinanceTransaction;
use App\Models\Product;
use App\Models\Sale;
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
        $this->assertSame(700, $sale->cash_received);
        $this->assertSame(200, $sale->change);
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

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'unit_price' => 100,
            'discount' => 0,
            'subtotal' => 100,
        ]);
    }

    private function pendingSale(User $user, int $total): Sale
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
