<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_products_are_rejected_when_creating_a_purchase_order(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Test Vendor',
            'contact_person' => 'Vendor Person',
            'email' => 'vendor@test.com',
            'phone' => '1234567890',
        ]);

        $company = Company::create([
            'company_code' => 'BRD-001',
            'company_name' => 'Test Brand',
            'short_name' => 'TB',
        ]);

        $supplier->companies()->attach($company->id);

        $category = Category::create([
            'name' => 'Makeup',
            'slug' => 'makeup',
        ]);

        $unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        $product = Product::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'mrp' => 500,
            'purchase_price' => 400,
        ]);

        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'company_id' => $company->id,
                    'quantity' => 2,
                    'discount_percent' => 17,
                    'unit_price' => 415,
                    'selling_price' => 500,
                ],
                [
                    'product_id' => $product->id,
                    'company_id' => $company->id,
                    'quantity' => 5,
                    'discount_percent' => 17,
                    'unit_price' => 415,
                    'selling_price' => 500,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.product_id', 'items.1.product_id']);
        $this->assertDatabaseCount('purchases', 0);
    }
}
