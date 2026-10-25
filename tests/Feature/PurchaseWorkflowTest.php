<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Livewire\Purchases\PurchaseTable;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
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

    public function test_purchase_orders_export_without_html_tags(): void
    {
        $user = User::factory()->create(['name' => 'John Admin', 'role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Acme Supplies',
            'contact_person' => 'Vendor Person',
            'email' => 'vendor@test.com',
            'phone' => '1234567890',
        ]);

        $company = Company::create([
            'company_code' => 'BRD-001',
            'company_name' => 'Acme Brand',
            'short_name' => 'AB',
        ]);

        Purchase::create([
            'invoice_number' => 'PO-20261018-0001',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-10-18',
            'total' => 1500.00,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
        ]);

        $component = new PurchaseTable;
        $component->boot();
        $component->columns = $component->columns();
        $component->setUp();

        $currentHiddenStates = collect($component->columns)
            ->mapWithKeys(fn ($column) => [data_get($column, 'field') => data_get($column, 'hidden')]);

        $columnsWithHiddenState = array_map(function ($column) use ($currentHiddenStates) {
            $column->hidden = $currentHiddenStates[$column->field];

            return $column;
        }, $component->columns());

        $export = new Export;
        $prepared = $export->prepare($component->prepareToExport(), $columnsWithHiddenState, true);

        $this->assertNotEmpty($prepared['rows']);
        $row = $prepared['rows'][0];

        $this->assertSame('PO-20261018-0001', $row['PO Reference']);
        $this->assertSame('Acme Supplies', $row['Supplier / Vendor']);
        $this->assertSame('AB', $row['Brand / Company']);
        $this->assertSame('18/10/2026', $row['PO Date']);
        $this->assertSame('Received', $row['Status']);
        $this->assertSame('John Admin', $row['Created By']);

        foreach ($row as $key => $value) {
            $this->assertStringNotContainsString('<', $value, "Column {$key} contains HTML tags: {$value}");
            $this->assertStringNotContainsString('>', $value, "Column {$key} contains HTML tags: {$value}");
        }
    }

    public function test_product_search_endpoint_supports_case_insensitive_sku_and_scanner_inputs(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $company = Company::create([
            'company_code' => 'BRD-001',
            'company_name' => 'Lakme',
            'short_name' => 'Lakme',
        ]);

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
            'sku' => 'COS.0001',
            'name' => 'LuxeGlow HD Foundation',
            'mrp' => 899,
            'is_active' => true,
        ]);

        // 1. Lowercase search should find uppercase SKU
        $response = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'q' => 'cos.0001',
            'for_purchase' => true,
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame($product->id, $data[0]['value']);
        $this->assertSame('COS.0001', $data[0]['sku']);

        // 2. Barcode scanner input with whitespace/newline
        $responseScanner = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'q' => "  COS.0001\r\n ",
            'for_purchase' => true,
        ]);

        $responseScanner->assertOk();
        $dataScanner = $responseScanner->json();
        $this->assertCount(1, $dataScanner);
        $this->assertSame($product->id, $dataScanner[0]['value']);
    }

    public function test_product_search_endpoint_filters_by_supplier_brands(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Mega Vendor',
            'contact_person' => 'Vendor Person',
            'email' => 'mega@test.com',
            'phone' => '1234567890',
        ]);

        $brandA = Company::create(['company_code' => 'BRD-A', 'company_name' => 'Brand A']);
        $brandB = Company::create(['company_code' => 'BRD-B', 'company_name' => 'Brand B']);

        $supplier->companies()->attach([$brandA->id]);

        $category = Category::create(['name' => 'Makeup', 'slug' => 'makeup-2']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc2']);

        $productA = Product::factory()->create([
            'company_id' => $brandA->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-A',
            'name' => 'Product A',
            'is_active' => true,
        ]);

        $productB = Product::factory()->create([
            'company_id' => $brandB->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-B',
            'name' => 'Product B',
            'is_active' => true,
        ]);

        // When supplier_id is provided, only brands supplied by that vendor are returned
        $response = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'supplier_id' => $supplier->id,
            'for_purchase' => true,
        ]);

        $response->assertOk();
        $ids = collect($response->json())->pluck('value')->all();
        $this->assertContains($productA->id, $ids);
        $this->assertNotContains($productB->id, $ids);
    }

    public function test_product_search_endpoint_searches_by_brand_name(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $brand = Company::create(['company_code' => 'BRD-LAKME', 'company_name' => 'Lakme Cosmetics', 'short_name' => 'Lakme']);
        $category = Category::create(['name' => 'Makeup', 'slug' => 'makeup-lakme']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc-lakme']);

        $product = Product::factory()->create([
            'company_id' => $brand->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'LAK.001',
            'name' => 'Eyeconic Kajal',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'q' => 'Lakme',
            'for_purchase' => true,
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertSame($product->id, $data[0]['value']);
        $this->assertSame('Lakme', $data[0]['brand']);
    }

    public function test_purchase_create_view_renders_table_with_single_product_select_and_brand_column(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('purchases.create'));

        $response->assertOk();
        $response->assertSee('Product');
        $response->assertSee('Brand');
        $response->assertSee(':id="`product_search_${item.key}`"', false);
        $response->assertDontSee(':id="`company_id_${item.key}`"', false);
        $response->assertSee('x-text="item.brand || \'-\'"', false);
    }

    public function test_purchase_can_be_created_with_products_from_vendor_brands_auto_populating_company_id(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Supplier X',
            'contact_person' => 'Supplier Contact',
            'email' => 'supplierx@test.com',
            'phone' => '9876543210',
        ]);

        $brand = Company::create(['company_code' => 'BRD-X', 'company_name' => 'Brand X']);
        $supplier->companies()->attach([$brand->id]);

        $category = Category::create(['name' => 'Skincare', 'slug' => 'skincare-x']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc-x']);

        $product = Product::factory()->create([
            'company_id' => $brand->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-X1',
            'name' => 'Product X1',
            'mrp' => 1000,
            'is_active' => true,
        ]);

        // company_id omitted in request body; prepareForValidation should infer from first product
        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'company_id' => $brand->id,
                    'quantity' => 10,
                    'discount_percent' => 20,
                    'unit_price' => 800,
                    'selling_price' => 1000,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchases', [
            'supplier_id' => $supplier->id,
            'company_id' => $brand->id,
            'total' => 8000,
        ]);
    }

    public function test_custom_mrp_discount_is_saved_and_preserved_on_edit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create([
            'name' => 'Supplier Custom Discount',
            'contact_person' => 'John Doe',
            'phone' => '1234567890',
            'email' => 'supplier_discount@test.com',
        ]);
        $brand = Company::create(['company_code' => 'BRD-DISC', 'company_name' => 'Brand Discount']);
        $supplier->companies()->attach($brand->id);

        $category = Category::create(['name' => 'Makeup Disc', 'slug' => 'makeup-disc']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs']);

        $product = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'company_id' => $brand->id,
            'sku' => 'SKU-DISC-01',
            'name' => 'Palette Special',
            'mrp' => 1299,
            'is_active' => true,
        ]);

        // Submit PO with custom discount 25% (different from default 17%)
        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'company_id' => $brand->id,
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'discount_percent' => 25,
                    'unit_price' => 974.25,
                    'selling_price' => 1299,
                ],
            ],
        ]);

        $response->assertRedirect();
        $purchase = Purchase::latest()->first();

        // 1299 * (1 - 0.25) = 974.25; 974.25 * 2 = 1948.50
        $this->assertEquals(1948.5, (float) $purchase->total);
        $this->assertEquals(974.25, (float) $purchase->items->first()->unit_price);

        // Edit view should preserve the 25% discount and not revert to 17%
        $editResponse = $this->actingAs($user)->get(route('purchases.edit', $purchase));
        $editResponse->assertOk();
        $editResponse->assertSee('25');
    }
}
