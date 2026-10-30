<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Livewire\Dashboard\Dashboard;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardKpiLoggerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_dashboard_kpi_logger_records_parameters_when_loading_yesterday(): void
    {
        $yesterday = Carbon::now()->subDay()->setTime(14, 0, 0);

        $company = Company::create(['company_name' => 'Cosmetics Co', 'company_code' => 'COMP01', 'status' => 'active']);
        $category = Category::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);

        $product = Product::create([
            'name' => 'Herbal Shampoo',
            'sku' => 'SHAMP-01',
            'product_code' => 'PRD-SHAMP-01',
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'mrp' => 500.00,
            'selling_price' => 450.00,
            'purchase_price' => 300.00,
            'quantity' => 20,
            'is_active' => true,
        ]);

        $service = Service::create([
            'name' => 'Hair Spa',
            'code' => 'SRV-SPA-01',
            'cost' => 600.00,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV.261029.0001',
            'customer_id' => null,
            'created_by' => $this->admin->id,
            'sale_date' => $yesterday,
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1500.00,
            'global_discount' => 0.00,
            'coupon_discount' => 0.00,
            'round_off' => 0.00,
            'total_discount' => 50.00,
            'total' => 1450.00,
            'cash_received' => 1500.00,
            'change' => 50.00,
            'payment_method' => PaymentMethod::CASH,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'cost_price' => 300.00,
            'unit_price' => 450.00,
            'discount' => 25.00,
            'final_price' => 425.00,
            'subtotal' => 850.00,
        ]);

        $sale->items()->create([
            'service_id' => $service->id,
            'quantity' => 1,
            'cost_price' => 0.00,
            'unit_price' => 600.00,
            'discount' => 0.00,
            'final_price' => 600.00,
            'subtotal' => 600.00,
        ]);

        Log::shouldReceive('info')
            ->atLeast()->once()
            ->withArgs(function ($message) use ($sale) {
                return is_string($message)
                    && str_contains($message, '[DASHBOARD KPI AUDIT]')
                    && str_contains($message, $sale->invoice_number)
                    && str_contains($message, 'Herbal Shampoo')
                    && str_contains($message, 'Hair Spa')
                    && str_contains($message, 'COGS')
                    && str_contains($message, 'Gross Profit');
            });

        Log::shouldReceive('error')->never();

        Livewire::actingAs($this->admin)
            ->test(Dashboard::class)
            ->set('dateFilter', 'yesterday')
            ->assertOk();
    }

    public function test_artisan_dashboard_audit_kpi_command_outputs_report(): void
    {
        $this->artisan('dashboard:audit-kpi', ['period' => 'yesterday'])
            ->expectsOutputToContain('[DASHBOARD KPI AUDIT] Period: YESTERDAY')
            ->expectsOutputToContain('FINAL DASHBOARD KPIS FOR YESTERDAY')
            ->assertSuccessful();
    }
}
