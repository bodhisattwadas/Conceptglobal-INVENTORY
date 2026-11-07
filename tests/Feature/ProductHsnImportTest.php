<?php

namespace Tests\Feature;

use App\Livewire\Products\ProductHsnImport;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductHsnImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ProductHsnImportTest extends TestCase
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
        $this->category = Category::create(['name' => 'Skin Care', 'slug' => 'skin-care']);
        $this->unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
        $this->company = Company::create(['company_code' => 'COMP-HSN', 'company_name' => 'HSN Brand']);
    }

    public function test_can_download_hsn_template_with_all_existing_products(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'name' => 'Product Alpha',
            'product_code' => '8901234567890',
            'sku' => 'P.ALPHA.001',
            'hsn_code' => '33041000',
        ]);

        $response = $this->actingAs($this->admin)->get(route('products.hsn-template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_service_generates_and_imports_xlsx_correctly(): void
    {
        $service = app(ProductHsnImportService::class);

        $product1 = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'name' => 'Product 1',
            'product_code' => 'CODE-111',
            'sku' => 'SKU-111',
            'hsn_code' => null,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'name' => 'Product 2',
            'product_code' => 'CODE-222',
            'sku' => 'SKU-222',
            'hsn_code' => '11112222',
        ]);

        // Generate an XLSX to simulate user filling in HSN codes
        $tempPath = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($tempPath);
        $writer->addRow(Row::fromValues(['Product Code', 'HSN Code', 'Product Name', 'SKU']));
        $writer->addRow(Row::fromValues(['CODE-111', '33049900', 'Product 1', 'SKU-111']));
        $writer->addRow(Row::fromValues(['CODE-222', '33049900', 'Product 2', 'SKU-222']));
        $writer->addRow(Row::fromValues(['NON-EXISTENT', '99999999', 'Fake', '']));
        $writer->close();

        $result = $service->importFromPath($tempPath, 'xlsx');
        @unlink($tempPath);

        $this->assertEquals(3, $result['total_rows']);
        $this->assertEquals(2, $result['updated_count']);
        $this->assertEquals(0, $result['unchanged_count']);
        $this->assertEquals(1, $result['failed_count']);
        $this->assertNotEmpty($result['errors']);

        $this->assertEquals('33049900', $product1->fresh()->hsn_code);
        $this->assertEquals('33049900', $product2->fresh()->hsn_code);
    }

    public function test_service_imports_csv_correctly(): void
    {
        $service = app(ProductHsnImportService::class);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'name' => 'Product CSV',
            'product_code' => 'CSV-999',
            'sku' => 'SKU-CSV',
            'hsn_code' => null,
        ]);

        $csvPath = tempnam(sys_get_temp_dir(), 'test_csv_').'.csv';
        $csvContent = "Product Code,HSN Code,Product Name,SKU\nCSV-999,33051010,Product CSV,SKU-CSV\n";
        file_put_contents($csvPath, $csvContent);

        $result = $service->importFromPath($csvPath, 'csv');
        @unlink($csvPath);

        $this->assertEquals(1, $result['total_rows']);
        $this->assertEquals(1, $result['updated_count']);
        $this->assertEquals('33051010', $product->fresh()->hsn_code);
    }

    public function test_livewire_component_handles_upload_and_dispatches_events(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'name' => 'Livewire Product',
            'product_code' => 'LW-1234',
            'sku' => 'SKU-LW',
            'hsn_code' => null,
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'lw_import_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($tempPath);
        $writer->addRow(Row::fromValues(['Product Code', 'HSN Code', 'Product Name', 'SKU']));
        $writer->addRow(Row::fromValues(['LW-1234', '84439900', 'Livewire Product', 'SKU-LW']));
        $writer->close();

        $uploadedFile = UploadedFile::fake()->createWithContent(
            'sync_hsn.xlsx',
            file_get_contents($tempPath)
        );

        Livewire::actingAs($this->admin)
            ->test(ProductHsnImport::class)
            ->call('openModal')
            ->assertDispatched('open-modal', name: 'product-hsn-import-modal')
            ->set('file', $uploadedFile)
            ->call('import')
            ->assertDispatched('pg:eventRefresh-product-table')
            ->assertDispatched('toast')
            ->assertHasNoErrors();

        $this->assertEquals('84439900', $product->fresh()->hsn_code);
        @unlink($tempPath);
    }
}
