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
        $this->assertSame('Shop', $row['Location']);
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

    public function test_purchase_create_view_defaults_mrp_discount_to_zero_percent(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('purchases.create'));

        $response->assertOk();
        $response->assertSee('defaultDiscountPercent: 0', false);
    }

    public function test_product_search_supports_name_barcode_both_and_returns_barcode_name_format(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $company = Company::create([
            'company_code' => 'BRD-STR',
            'company_name' => 'Berry Brand',
            'short_name' => 'Berry',
        ]);

        $category = Category::create([
            'name' => 'Body Care',
            'slug' => 'body-care',
        ]);

        $unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        $product = Product::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-STRAW-1',
            'product_code' => '8906036215017',
            'name' => 'Strawberry Hydrating & Softening Body Wash',
            'mrp' => 450,
            'is_active' => true,
        ]);

        // 1. Search by name alone
        $resName = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'q' => 'Strawberry',
            'for_purchase' => true,
        ]);
        $resName->assertOk();
        $dataName = $resName->json();
        $this->assertCount(1, $dataName);
        $this->assertSame($product->id, $dataName[0]['value']);
        $this->assertSame('8906036215017 - Strawberry Hydrating & Softening Body Wash', $dataName[0]['text']);
        $this->assertSame('8906036215017', $dataName[0]['product_code']);

        // 2. Search by barcode alone
        $resBarcode = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'q' => '8906036215017',
            'for_purchase' => true,
        ]);
        $resBarcode->assertOk();
        $dataBarcode = $resBarcode->json();
        $this->assertCount(1, $dataBarcode);
        $this->assertSame($product->id, $dataBarcode[0]['value']);
        $this->assertSame('8906036215017 - Strawberry Hydrating & Softening Body Wash', $dataBarcode[0]['text']);

        // 3. Search by both (barcode - name)
        $resBothHyphen = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'q' => '8906036215017 - Strawberry',
            'for_purchase' => true,
        ]);
        $resBothHyphen->assertOk();
        $dataBothHyphen = $resBothHyphen->json();
        $this->assertCount(1, $dataBothHyphen);
        $this->assertSame($product->id, $dataBothHyphen[0]['value']);

        // 4. Search by both (barcode space name)
        $resBothSpace = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'q' => '8906036215017 Strawberry',
            'for_purchase' => true,
        ]);
        $resBothSpace->assertOk();
        $dataBothSpace = $resBothSpace->json();
        $this->assertCount(1, $dataBothSpace);
        $this->assertSame($product->id, $dataBothSpace[0]['value']);

        // 5. Search by both (barcode-name without spaces around hyphen)
        $resBothJoined = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'q' => '8906036215017-Strawberry',
            'for_purchase' => true,
        ]);
        $resBothJoined->assertOk();
        $dataBothJoined = $resBothJoined->json();
        $this->assertCount(1, $dataBothJoined);
        $this->assertSame($product->id, $dataBothJoined[0]['value']);
    }

    public function test_purchase_create_view_supports_product_code_search_and_barcode_name_format(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('purchases.create'));

        $response->assertOk();
        $response->assertSee('searchField: [\'text\', \'name\', \'product_code\', \'sku\', \'brand\']', false);
        $response->assertSee('formatProductDisplay', false);
        $response->assertSee('Search product name, barcode or SKU...', false);
    }

    public function test_purchase_create_view_renders_location_selection_with_shop_as_default(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('purchases.create'));

        $response->assertOk();
        $response->assertSee('Location');
        $response->assertSee('value="shop"', false);
        $response->assertSee('value="parlour"', false);
        $response->assertSee('location: \'shop\'', false);
    }

    public function test_purchase_can_be_created_with_shop_location_by_default(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Supplier Loc',
            'contact_person' => 'Contact',
            'email' => 'loc@test.com',
            'phone' => '9988776655',
        ]);

        $brand = Company::create(['company_code' => 'BRD-LOC', 'company_name' => 'Brand Loc']);
        $supplier->companies()->attach([$brand->id]);

        $category = Category::create(['name' => 'Cosmetics', 'slug' => 'cosmetics-loc']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc-loc']);

        $product = Product::factory()->create([
            'company_id' => $brand->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-LOC-1',
            'name' => 'Product Loc 1',
            'mrp' => 500,
            'is_active' => true,
        ]);

        // When location is not specified, it defaults to 'shop'
        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'company_id' => $brand->id,
                    'quantity' => 5,
                    'discount_percent' => 0,
                    'unit_price' => 500,
                    'selling_price' => 500,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchases', [
            'supplier_id' => $supplier->id,
            'location' => 'shop',
        ]);
    }

    public function test_purchase_can_be_created_with_parlour_location(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Supplier Parlour',
            'contact_person' => 'Contact',
            'email' => 'parlour@test.com',
            'phone' => '9988776654',
        ]);

        $brand = Company::create(['company_code' => 'BRD-PAR', 'company_name' => 'Brand Parlour']);
        $supplier->companies()->attach([$brand->id]);

        $category = Category::create(['name' => 'Parlour Items', 'slug' => 'parlour-items']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc-par']);

        $product = Product::factory()->create([
            'company_id' => $brand->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-PAR-1',
            'name' => 'Facial Kit',
            'mrp' => 1200,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'location' => 'parlour',
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'company_id' => $brand->id,
                    'quantity' => 2,
                    'discount_percent' => 10,
                    'unit_price' => 1080,
                    'selling_price' => 1200,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchases', [
            'supplier_id' => $supplier->id,
            'location' => 'parlour',
        ]);
    }

    public function test_purchase_location_can_be_updated_and_reflected_in_views(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Supplier Update Loc',
            'contact_person' => 'Contact',
            'email' => 'update@test.com',
            'phone' => '9988776653',
        ]);

        $brand = Company::create(['company_code' => 'BRD-UPD', 'company_name' => 'Brand Upd']);
        $supplier->companies()->attach([$brand->id]);

        $category = Category::create(['name' => 'Category Upd', 'slug' => 'category-upd']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc-upd']);

        $product = Product::factory()->create([
            'company_id' => $brand->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-UPD-1',
            'name' => 'Salon Shampoo',
            'mrp' => 800,
            'is_active' => true,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-TEST-LOC',
            'supplier_id' => $supplier->id,
            'company_id' => $brand->id,
            'location' => 'shop',
            'purchase_date' => '2026-10-27',
            'total' => 800,
            'created_by' => $user->id,
        ]);

        // 1. Check edit page preselects existing location
        $editResponse = $this->actingAs($user)->get(route('purchases.edit', $purchase));
        $editResponse->assertOk();
        $editResponse->assertSee('location: \'shop\'', false);

        // 2. Update to parlour
        $updateResponse = $this->actingAs($user)->put(route('purchases.update', $purchase), [
            'supplier_id' => $supplier->id,
            'company_id' => $brand->id,
            'location' => 'parlour',
            'purchase_date' => '2026-10-27',
            'items' => [
                [
                    'product_id' => $product->id,
                    'company_id' => $brand->id,
                    'quantity' => 1,
                    'discount_percent' => 0,
                    'unit_price' => 800,
                    'selling_price' => 800,
                ],
            ],
        ]);

        $updateResponse->assertRedirect(route('purchases.show', $purchase));
        $purchase->refresh();
        $this->assertSame('parlour', $purchase->location->value);

        // 3. Show view reflects parlour
        $showResponse = $this->actingAs($user)->get(route('purchases.show', $purchase));
        $showResponse->assertOk();
        $showResponse->assertSee('Parlour');

        // 4. Print view renders location and PDF download succeeds
        $printHtml = view('purchases.print', ['purchase' => $purchase])->render();
        $this->assertStringContainsString('Location', $printHtml);
        $this->assertStringContainsString('Parlour', $printHtml);

        $printResponse = $this->actingAs($user)->get(route('purchases.print', $purchase));
        $printResponse->assertOk();
        $printResponse->assertHeader('content-type', 'application/pdf');
    }
}
