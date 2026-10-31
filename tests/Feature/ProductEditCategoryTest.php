<?php

namespace Tests\Feature;

use App\Enums\PurchaseLocation;
use App\Livewire\Products\ProductForm;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductEditCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_edit_product_and_change_category_without_enum_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $category1 = Category::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
        $category2 = Category::create(['name' => 'Skin Care', 'slug' => 'skin-care']);

        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
        $company = Company::create(['company_code' => 'COMP-1', 'company_name' => 'Brand Test']);

        $product = Product::factory()->create([
            'category_id' => $category1->id,
            'unit_id' => $unit->id,
            'company_id' => $company->id,
            'name' => 'Test Shampoo',
            'mrp' => 350,
            'selling_price' => 350,
            'purchase_price' => 200,
            'quantity' => 10,
            'min_stock' => 2,
            'location' => PurchaseLocation::SHOP,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ProductForm::class)
            ->call('edit', $product)
            ->set('category_id', $category2->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal');

        $this->assertEquals($category2->id, $product->fresh()->category_id);
    }
}
