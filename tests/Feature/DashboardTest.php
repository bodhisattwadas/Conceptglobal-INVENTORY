<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Livewire\Dashboard\Dashboard;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    private Category $category;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->company = Company::create([
            'company_code' => 'CO01',
            'company_name' => 'Concept Global',
        ]);

        $this->category = Category::create([
            'name' => 'Cosmetics',
            'slug' => 'cosmetics',
        ]);

        $this->unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pcs',
        ]);
    }

    public function test_dashboard_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeLivewire(Dashboard::class);
    }

    public function test_dashboard_top_products_handles_service_items_with_null_product_id(): void
    {
        $service = Service::create([
            'name' => 'Hair Styling',
            'cost' => 50,
            'price' => 150,
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Shampoo Bottle',
            'sku' => 'SHAMP-01',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'buying_price' => 50,
            'selling_price' => 100,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-DASH-001',
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'created_by' => $this->admin->id,
            'total' => 250,
            'subtotal' => 250,
            'cash_received' => 250,
            'change' => 0,
        ]);

        // Sale item for product
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'service_id' => null,
            'quantity' => 2,
            'cost_price' => 50,
            'unit_price' => 100,
            'subtotal' => 200,
            'final_price' => 100,
        ]);

        // Sale item for service (null product_id)
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => null,
            'service_id' => $service->id,
            'quantity' => 1,
            'cost_price' => 50,
            'unit_price' => 50,
            'subtotal' => 50,
            'final_price' => 50,
        ]);

        $statsService = app(DashboardStatsService::class);
        $topProducts = $statsService->getTopProducts(now()->startOfDay(), now()->endOfDay());

        $this->assertCount(1, $topProducts);
        $this->assertEquals('Shampoo Bottle', $topProducts[0]['product_name']);
        $this->assertEquals('SHAMP-01', $topProducts[0]['sku']);
        $this->assertEquals(2, $topProducts[0]['total_sold']);

        // Test Livewire component directly
        Livewire::actingAs($this->admin)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Shampoo Bottle');
    }

    public function test_kpi_cards_exclude_services_from_cogs_and_accurately_calculate_profit(): void
    {
        $service = Service::create([
            'name' => 'Hair Coloring',
            'cost' => 1000,
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Conditioner',
            'sku' => 'COND-01',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'purchase_price' => 200,
            'selling_price' => 500,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-DASH-KPI-01',
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'created_by' => $this->admin->id,
            'total' => 1500,
            'subtotal' => 1500,
            'cash_received' => 1500,
            'change' => 0,
        ]);

        // Product sold: Qty 1, Unit Price 500, Cost Price 200 -> COGS 200
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'service_id' => null,
            'quantity' => 1,
            'cost_price' => 200,
            'unit_price' => 500,
            'subtotal' => 500,
            'final_price' => 500,
        ]);

        // Service sold: Qty 1, Unit Price 1000, Cost Price 0.00 -> COGS 0
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => null,
            'service_id' => $service->id,
            'quantity' => 1,
            'cost_price' => 0,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'final_price' => 1000,
        ]);

        $statsService = app(DashboardStatsService::class);
        $stats = $statsService->getSalesStats(now()->startOfDay(), now()->endOfDay(), 'today');

        $this->assertEquals(1500.0, $stats['total_revenue']);
        $this->assertEquals(1, $stats['count']);
        $this->assertEquals(200.0, $stats['cogs']);
        $this->assertEquals(1300.0, $stats['gross_profit']);
        $this->assertEquals(86.7, $stats['margin']);

        // Verify Livewire renders correctly
        Livewire::actingAs($this->admin)
            ->test(Dashboard::class)
            ->set('dateFilter', 'today')
            ->assertOk()
            ->assertSee('1,500.00')
            ->assertSee('1,300.00')
            ->assertSee('86.7% margin');
    }

    public function test_dashboard_supports_all_time_date_period(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Dashboard::class)
            ->set('dateFilter', 'all_time')
            ->assertOk();
    }
}
