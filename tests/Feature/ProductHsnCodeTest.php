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

class ProductHsnCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private Unit $unit;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = Category::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
        $this->unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
        $this->company = Company::create(['company_code' => 'COMP-1', 'company_name' => 'Brand Test']);
    }

    public function test_can_create_product_with_hsn_code(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProductForm::class)
            ->call('create')
            ->set('name', 'Herbal Shampoo')
            ->set('category_id', $this->category->id)
            ->set('unit_id', $this->unit->id)
            ->set('company_id', $this->company->id)
            ->set('mrp', 250)
            ->set('min_stock', 5)
            ->set('location', 'shop')
            ->set('hsn_code', '33051000')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal');

        $this->assertDatabaseHas('products', [
            'name' => 'Herbal Shampoo',
            'hsn_code' => '33051000',
        ]);
    }

    public function test_can_edit_product_hsn_code(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'name' => 'Lip Balm',
            'mrp' => 150,
            'hsn_code' => '33041000',
            'location' => PurchaseLocation::SHOP,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProductForm::class)
            ->call('edit', $product)
            ->assertSet('hsn_code', '33041000')
            ->set('hsn_code', '33049900')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal');

        $this->assertEquals('33049900', $product->fresh()->hsn_code);
    }

    public function test_can_clear_product_hsn_code_in_edit(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'name' => 'Body Lotion',
            'mrp' => 300,
            'hsn_code' => '33049990',
            'location' => PurchaseLocation::SHOP,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProductForm::class)
            ->call('edit', $product)
            ->set('hsn_code', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal');

        $this->assertNull($product->fresh()->hsn_code);
    }

    public function test_clone_product_preserves_hsn_code(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'name' => 'Face Cream',
            'mrp' => 450,
            'hsn_code' => '33049910',
            'location' => PurchaseLocation::SHOP,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProductForm::class)
            ->call('cloneProduct', $product)
            ->assertSet('hsn_code', '33049910')
            ->set('name', 'Face Cream Cloned')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal');

        $this->assertDatabaseHas('products', [
            'name' => 'Face Cream Cloned',
            'hsn_code' => '33049910',
        ]);
    }
}
