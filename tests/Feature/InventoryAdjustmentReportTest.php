<?php

namespace Tests\Feature;

use App\Livewire\Reports\InventoryAdjustmentReport;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryAdjustmentReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryAdjustmentReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected User $sales;

    protected Product $productA;

    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->sales = User::factory()->create(['role' => 'sales']);

        $supplier = Supplier::factory()->create(['name' => 'Supplier One']);
        $brand = Company::create([
            'company_code' => 'BRD-001',
            'company_name' => 'Brand Alpha',
            'short_name' => 'Alpha',
        ]);
        $supplier->companies()->attach($brand->id);

        $category = Category::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);

        $this->productA = Product::factory()->create([
            'company_id' => $brand->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Shampoo 250ml',
            'sku' => 'SKU-SHAMP-01',
            'purchase_price' => 150.00,
            'quantity' => 20,
        ]);

        $this->productB = Product::factory()->create([
            'company_id' => $brand->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Conditioner 250ml',
            'sku' => 'SKU-COND-01',
            'purchase_price' => 200.00,
            'quantity' => 15,
        ]);
    }

    private function createAdjustment(array $attributes = []): InventoryAdjustment
    {
        static $seq = 1;

        return InventoryAdjustment::create(array_merge([
            'adjustment_number' => 'ADJ.261114.'.str_pad((string) $seq++, 4, '0', STR_PAD_LEFT),
            'type' => 'quantity_decrease',
            'source_product_id' => $this->productA->id,
            'source_location' => 'shop',
            'target_location' => 'shop',
            'quantity' => 1,
            'unit_cost' => 100.00,
            'reason' => 'damage',
            'notes' => null,
            'created_by' => $this->admin->id,
            'created_at' => Carbon::now(),
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.adjustments'))->assertRedirect(route('login'));
        $this->get(route('reports.adjustments.pdf'))->assertRedirect(route('login'));
        $this->get(route('reports.adjustments.csv'))->assertRedirect(route('login'));
    }

    public function test_sales_role_is_forbidden(): void
    {
        $this->actingAs($this->sales)
            ->get(route('reports.adjustments'))
            ->assertForbidden();

        $this->actingAs($this->sales)
            ->get(route('reports.adjustments.pdf'))
            ->assertForbidden();

        $this->actingAs($this->sales)
            ->get(route('reports.adjustments.csv'))
            ->assertForbidden();
    }

    public function test_admin_and_manager_can_access_report_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.adjustments'))
            ->assertOk()
            ->assertSeeLivewire(InventoryAdjustmentReport::class);

        $this->actingAs($this->manager)
            ->get(route('reports.adjustments'))
            ->assertOk()
            ->assertSeeLivewire(InventoryAdjustmentReport::class);
    }

    public function test_kpi_summary_calculates_write_off_and_surplus_totals_accurately(): void
    {
        $service = app(InventoryAdjustmentReportService::class);
        $start = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();

        // 1. Damage write-off: 3 units @ 150 = 450
        $this->createAdjustment([
            'source_product_id' => $this->productA->id,
            'type' => 'quantity_decrease',
            'quantity' => 3,
            'unit_cost' => 150.00,
            'reason' => 'damage',
            'source_location' => 'shop',
            'target_location' => 'shop',
            'created_at' => Carbon::now(),
        ]);

        // 2. Expired write-off: 2 units @ 200 = 400
        $this->createAdjustment([
            'source_product_id' => $this->productB->id,
            'type' => 'quantity_decrease',
            'quantity' => 2,
            'unit_cost' => 200.00,
            'reason' => 'expired',
            'source_location' => 'parlour',
            'target_location' => 'parlour',
            'created_at' => Carbon::now(),
        ]);

        // 3. Surplus / Found: 5 units @ 150 = 750
        $this->createAdjustment([
            'source_product_id' => $this->productA->id,
            'type' => 'quantity_increase',
            'quantity' => 5,
            'unit_cost' => 150.00,
            'reason' => 'found_stock',
            'source_location' => 'shop',
            'target_location' => 'shop',
            'created_at' => Carbon::now(),
        ]);

        $summary = $service->getKpiSummary($start, $end);

        // Assert write-off totals: 3 + 2 = 5 units, 450 + 400 = 850
        $this->assertEquals(5, $summary['damage_units']);
        $this->assertEquals(850.00, $summary['damage_amount']);

        // Assert surplus totals: 5 units, 750
        $this->assertEquals(5, $summary['surplus_units']);
        $this->assertEquals(750.00, $summary['surplus_amount']);

        // Assert net impact: 750 - 850 = -100
        $this->assertEquals(-100.00, $summary['net_amount']);

        // Assert gross posted adjustments amount: 850 + 750 = 1600
        $this->assertEquals(1600.00, $summary['gross_amount']);

        // Assert total count
        $this->assertEquals(3, $summary['total_count']);
    }

    public function test_livewire_component_renders_and_filters_by_type(): void
    {
        $this->createAdjustment([
            'source_product_id' => $this->productA->id,
            'type' => 'quantity_decrease',
            'quantity' => 4,
            'unit_cost' => 150.00,
            'reason' => 'damage',
            'source_location' => 'shop',
            'target_location' => 'shop',
            'created_at' => Carbon::now(),
        ]);

        $this->createAdjustment([
            'source_product_id' => $this->productB->id,
            'type' => 'quantity_increase',
            'quantity' => 6,
            'unit_cost' => 200.00,
            'reason' => 'found_stock',
            'source_location' => 'shop',
            'target_location' => 'shop',
            'created_at' => Carbon::now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(InventoryAdjustmentReport::class)
            ->assertSee('Shampoo 250ml')
            ->assertSee('Conditioner 250ml')
            ->assertSee('600.00') // 4 * 150
            ->assertSee('1,200.00') // 6 * 200
            // Filter to Damage Write-offs only
            ->set('typeFilter', 'quantity_decrease')
            ->assertSee('Shampoo 250ml')
            ->assertDontSee('Conditioner 250ml')
            // Filter to Surplus only
            ->set('typeFilter', 'quantity_increase')
            ->assertSee('Conditioner 250ml')
            ->assertDontSee('Shampoo 250ml');
    }

    public function test_livewire_component_filters_by_location_and_search(): void
    {
        $this->createAdjustment([
            'source_product_id' => $this->productA->id,
            'type' => 'quantity_decrease',
            'quantity' => 2,
            'unit_cost' => 150.00,
            'reason' => 'damage',
            'source_location' => 'shop',
            'target_location' => 'shop',
            'created_at' => Carbon::now(),
        ]);

        $this->createAdjustment([
            'source_product_id' => $this->productB->id,
            'type' => 'quantity_decrease',
            'quantity' => 3,
            'unit_cost' => 200.00,
            'reason' => 'spillage',
            'source_location' => 'parlour',
            'target_location' => 'parlour',
            'created_at' => Carbon::now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(InventoryAdjustmentReport::class)
            ->set('locationFilter', 'parlour')
            ->assertSee('Conditioner 250ml')
            ->assertDontSee('Shampoo 250ml')
            ->set('locationFilter', 'all')
            ->set('search', 'Shampoo')
            ->assertSee('Shampoo 250ml')
            ->assertDontSee('Conditioner 250ml');
    }

    public function test_pdf_export_returns_successful_stream(): void
    {
        $this->createAdjustment([
            'source_product_id' => $this->productA->id,
            'type' => 'quantity_decrease',
            'quantity' => 2,
            'unit_cost' => 150.00,
            'reason' => 'damage',
            'source_location' => 'shop',
            'target_location' => 'shop',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.adjustments.pdf', [
                'period' => 'this_month',
            ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_csv_export_returns_streamed_file_with_adjustments(): void
    {
        $this->createAdjustment([
            'source_product_id' => $this->productA->id,
            'type' => 'quantity_decrease',
            'quantity' => 2,
            'unit_cost' => 150.00,
            'reason' => 'damage',
            'source_location' => 'shop',
            'target_location' => 'shop',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.adjustments.csv', [
                'period' => 'this_month',
            ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inventory-adjustments', $response->headers->get('Content-Disposition'));
    }
}
