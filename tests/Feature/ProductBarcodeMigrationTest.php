<?php

namespace Tests\Feature;

use App\DTOs\ProductData;
use App\Livewire\Products\ProductForm;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductBarcodeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    private Unit $unit;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->category = Category::create([
            'name' => 'Skincare',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $this->unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pcs',
            'is_active' => true,
        ]);

        $this->company = Company::create([
            'company_code' => 'COMP-001',
            'company_name' => 'Concept Global Brand',
            'short_name' => 'CGB',
            'is_active' => true,
        ]);
    }

    public function test_clean_barcodes_command_cleans_prefixed_product_names_safely(): void
    {
        // 1. Create a product with scanned barcode + dash in name
        $messyProduct = Product::create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'sku' => 'TEST-001',
            'name' => '8906036215017- Strawberry Hydrating & Softening Body Wash 250ml',
            'mrp' => 499,
            'purchase_price' => 499,
            'selling_price' => 499,
            'quantity' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        // 2. Create another product with en-dash and leading zeroes barcode
        $messyProduct2 = Product::create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'sku' => 'TEST-002',
            'name' => '012345678901 – Nourishing Night Cream 50g',
            'mrp' => 850,
            'purchase_price' => 850,
            'selling_price' => 850,
            'quantity' => 5,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        // 3. Create a normal product that contains a hyphen in shade name
        $normalProduct = Product::create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'sku' => 'TEST-003',
            'name' => 'LuxeGlow HD Matte Foundation - Warm Beige 30ml',
            'mrp' => 1200,
            'purchase_price' => 1200,
            'selling_price' => 1200,
            'quantity' => 15,
            'min_stock' => 3,
            'is_active' => true,
        ]);

        // Run dry-run first
        $this->artisan('products:clean-barcodes --dry-run')
            ->expectsOutputToContain('Found 2 candidate product(s).')
            ->expectsOutputToContain('DRY RUN: No database records were modified.')
            ->assertSuccessful();

        // Verify products were NOT modified during dry-run
        $this->assertEquals(
            '8906036215017- Strawberry Hydrating & Softening Body Wash 250ml',
            $messyProduct->fresh()->name
        );
        $this->assertNull($messyProduct->fresh()->product_code);

        // Run actual execution with --force
        $this->artisan('products:clean-barcodes --force')
            ->expectsOutputToContain('Successfully cleaned and updated 2 product(s)!')
            ->assertSuccessful();

        // Verify messy products were cleaned
        $messyProduct->refresh();
        $this->assertEquals('8906036215017', $messyProduct->product_code);
        $this->assertEquals('Strawberry Hydrating & Softening Body Wash 250ml', $messyProduct->name);

        $messyProduct2->refresh();
        $this->assertEquals('012345678901', $messyProduct2->product_code);
        $this->assertEquals('Nourishing Night Cream 50g', $messyProduct2->name);

        // Verify normal product with hyphen was NOT touched
        $normalProduct->refresh();
        $this->assertNull($normalProduct->product_code);
        $this->assertEquals('LuxeGlow HD Matte Foundation - Warm Beige 30ml', $normalProduct->name);
    }

    public function test_product_service_creates_and_updates_with_product_code(): void
    {
        $this->actingAs($this->user);
        $service = app(ProductService::class);

        // Create product with product_code
        $dto = ProductData::fromArray([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'sku' => 'SKU-CODE-01',
            'product_code' => '8906036215017',
            'name' => 'Strawberry Hydrating Body Wash',
            'mrp' => 450,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $product = $service->createProduct($dto);
        $this->assertEquals('8906036215017', $product->product_code);
        $this->assertEquals('Strawberry Hydrating Body Wash', $product->name);

        // Update product code
        $updateDto = ProductData::fromArray([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'sku' => 'SKU-CODE-01',
            'product_code' => '8906036215999',
            'name' => 'Strawberry Hydrating Body Wash (Updated)',
            'mrp' => 450,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $updatedProduct = $service->updateProduct($product, $updateDto);
        $this->assertEquals('8906036215999', $updatedProduct->product_code);
        $this->assertEquals('Strawberry Hydrating Body Wash (Updated)', $updatedProduct->name);
    }

    public function test_product_search_endpoint_finds_and_prioritizes_barcode_match(): void
    {
        $this->actingAs($this->user);

        // Create product with product_code
        $product = Product::create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'sku' => 'SKU-BAR-99',
            'product_code' => '8906036215017',
            'name' => 'Strawberry Hydrating Body Wash',
            'mrp' => 499,
            'purchase_price' => 499,
            'selling_price' => 499,
            'quantity' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // Search by scanned barcode
        $response = $this->postJson(route('ajax.products.search'), [
            'q' => '8906036215017',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertNotEmpty($data);
        $this->assertEquals($product->id, $data[0]['id']);
        $this->assertEquals('8906036215017', $data[0]['product_code']);
        $this->assertEquals('Strawberry Hydrating Body Wash', $data[0]['name']);
    }

    public function test_livewire_product_form_saves_product_code(): void
    {
        $this->actingAs($this->user);

        Livewire::test(ProductForm::class)
            ->set('name', 'Rose Water Toner 200ml')
            ->set('product_code', '8906036215888')
            ->set('category_id', $this->category->id)
            ->set('unit_id', $this->unit->id)
            ->set('company_id', $this->company->id)
            ->set('mrp', 320)
            ->set('min_stock', 5)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $created = Product::where('product_code', '8906036215888')->first();
        $this->assertNotNull($created);
        $this->assertEquals('Rose Water Toner 200ml', $created->name);
        $this->assertEquals(320, $created->mrp);
    }

    public function test_livewire_product_form_shows_warning_when_duplicate_barcode_is_entered(): void
    {
        $this->actingAs($this->user);

        // Pre-existing product with barcode
        Product::create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'sku' => 'SKU-ORIGINAL',
            'product_code' => '8906036215017',
            'name' => 'Original Strawberry Wash',
            'mrp' => 500,
            'purchase_price' => 500,
            'selling_price' => 500,
            'quantity' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        Livewire::test(ProductForm::class)
            ->set('product_code', '8906036215017')
            ->assertSet('duplicateBarcodeWarning', "Warning: Barcode '8906036215017' is already assigned to 'Original Strawberry Wash' (SKU: SKU-ORIGINAL).")
            ->assertSee('Warning: Barcode')
            ->assertSee('Original Strawberry Wash')
            ->set('product_code', '8906036219999')
            ->assertSet('duplicateBarcodeWarning', null)
            ->assertDontSee('Warning: Barcode');
    }

    public function test_product_form_modal_has_cross_button_and_does_not_close_on_outside_click(): void
    {
        $this->actingAs($this->user);

        $test = Livewire::test(ProductForm::class);

        // 1. Cross button at top right is present and configured with close-modal
        $test->assertSee('aria-label="Close"', false);
        $test->assertSee('$dispatch(\'close-modal\', { name: \'product-form-modal\' })', false);

        // 2. Modal does not have outside-click close handlers
        $html = $test->html();
        $this->assertStringNotContainsString('x-on:click="show = false"', $html);

        // 3. Submitting the form successfully dispatches close-modal
        $test->set('name', 'Organic Aloe Gel 150ml')
            ->set('category_id', $this->category->id)
            ->set('unit_id', $this->unit->id)
            ->set('company_id', $this->company->id)
            ->set('mrp', 199)
            ->set('min_stock', 5)
            ->call('save')
            ->assertDispatched('close-modal', name: 'product-form-modal');
    }
}
