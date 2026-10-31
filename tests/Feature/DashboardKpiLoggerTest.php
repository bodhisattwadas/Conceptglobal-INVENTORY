<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Livewire\Dashboard\Dashboard;
use App\Models\Category;
use App\Models\Company;
use App\Models\DiscountCoupon;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

        InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $supplier = Supplier::factory()->create();
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->subDays(5),
            'created_by' => $this->admin->id,
            'total' => 1000,
        ]);

        $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 100,
            'selling_price' => 500,
            'subtotal' => 1000,
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

    public function test_coupon_edit_affects_dashboard_kpis(): void
    {
        $yesterday = Carbon::now()->subDay()->setTime(14, 0, 0);

        $coupon = DiscountCoupon::create([
            'code' => 'SAVE100',
            'type' => CouponType::Fixed,
            'value' => 100,
            'is_active' => true,
        ]);

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
            'purchase_price' => 100,
            'mrp' => 500,
            'price' => 500,
            'quantity' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $supplier = Supplier::factory()->create();
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->subDays(5),
            'created_by' => $this->admin->id,
            'total' => 1000,
        ]);

        $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 100,
            'selling_price' => 500,
            'subtotal' => 1000,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-001',
            'customer_id' => null,
            'sale_date' => $yesterday,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 500,
            'total_discount' => 0,
            'global_discount' => 0,
            'coupon_discount' => 0,
            'round_off' => 0,
            'total' => 500,
            'cash_received' => 500,
            'change' => 0,
            'status' => SaleStatus::COMPLETED,
            'created_by' => $this->admin->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 100,
            'unit_price' => 500,
            'discount' => 0,
            'final_price' => 500,
            'subtotal' => 500,
        ]);

        // Sync initial finance transaction
        app(FinanceTransactionService::class)->recordIncomeFromSale($sale);

        // 1. Initial Dashboard Check for yesterday
        $component = Livewire::actingAs($this->admin)
            ->test(Dashboard::class)
            ->set('dateFilter', 'yesterday');

        $this->assertEquals(500.0, $component->get('stats')['total_sales']);
        $this->assertEquals(400.0, $component->get('stats')['gross_profit']); // 500 - 100
        $this->assertEquals(500.0, $component->get('stats')['income']);

        // 2. Now edit the sale to apply coupon SAVE100
        $response = $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_date' => $yesterday->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'round_off' => 0,
            'cash_received' => 400,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'discount' => 0,
                ],
            ],
        ]);
        $response->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $this->assertEquals(400.0, (float) $sale->total);
        $this->assertEquals(100.0, (float) $sale->coupon_discount);

        // 3. Load Dashboard again for yesterday (cache should be automatically invalidated)
        $component2 = Livewire::actingAs($this->admin)
            ->test(Dashboard::class)
            ->set('dateFilter', 'yesterday');

        $this->assertEquals(400.0, $component2->get('stats')['total_sales']);
        $this->assertEquals(300.0, $component2->get('stats')['gross_profit']); // 400 - 100
        $this->assertEquals(400.0, $component2->get('stats')['income']);
        $this->assertEquals(100.0, $component2->get('stats')['coupon_discount']);

        // 4. Test refreshDashboard action clears cache
        $component2->call('refreshDashboard');
        $this->assertEquals(400.0, $component2->get('stats')['total_sales']);
    }
}
