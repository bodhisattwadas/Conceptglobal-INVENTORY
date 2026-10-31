<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Livewire\Reports\SalesReport;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function createSale(array $attributes = []): Sale
    {
        static $counter = 1;

        return Sale::create(array_merge([
            'invoice_number' => 'INV-TEST-'.str_pad((string) $counter++, 4, '0', STR_PAD_LEFT),
            'customer_id' => null,
            'created_by' => 1,
            'sale_date' => Carbon::now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1000.00,
            'global_discount' => 0.00,
            'coupon_discount' => 0.00,
            'round_off' => 0.00,
            'total_discount' => 0.00,
            'total' => 1000.00,
            'cash_received' => 1000.00,
            'change' => 0.00,
            'payment_method' => PaymentMethod::CASH,
            'notes' => null,
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.sales'))->assertRedirect(route('login'));
        $this->get(route('reports.sales.pdf'))->assertRedirect(route('login'));
    }

    public function test_non_admin_roles_are_forbidden(): void
    {
        $salesUser = User::factory()->create(['role' => 'sales']);
        $managerUser = User::factory()->create(['role' => 'manager']);

        $this->actingAs($salesUser)->get(route('reports.sales'))->assertForbidden();
        $this->actingAs($salesUser)->get(route('reports.sales.pdf'))->assertForbidden();

        $this->actingAs($managerUser)->get(route('reports.sales'))->assertForbidden();
        $this->actingAs($managerUser)->get(route('reports.sales.pdf'))->assertForbidden();
    }

    public function test_admin_can_view_sales_report_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('reports.sales'));

        $response->assertOk();
        $response->assertSeeLivewire(SalesReport::class);
        $response->assertSee('Sales & Revenue Reports');
    }

    public function test_sales_report_kpi_and_payment_method_breakdown_calculations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $now = Carbon::now();

        // Sale 1: UPI
        $this->createSale([
            'created_by' => $admin->id,
            'sale_date' => $now,
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1000.00,
            'total_discount' => 100.00,
            'coupon_discount' => 50.00,
            'total' => 850.00,
            'payment_method' => PaymentMethod::UPI,
        ]);

        // Sale 2: Cash
        $this->createSale([
            'created_by' => $admin->id,
            'sale_date' => $now,
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 500.00,
            'total_discount' => 0.00,
            'coupon_discount' => 0.00,
            'total' => 500.00,
            'payment_method' => PaymentMethod::CASH,
        ]);

        // Sale 3: Card
        $this->createSale([
            'created_by' => $admin->id,
            'sale_date' => $now,
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 2000.00,
            'total_discount' => 200.00,
            'coupon_discount' => 0.00,
            'total' => 1800.00,
            'payment_method' => PaymentMethod::CARD,
        ]);

        // Sale 4: Bank Transfer
        $this->createSale([
            'created_by' => $admin->id,
            'sale_date' => $now,
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 3000.00,
            'total_discount' => 0.00,
            'coupon_discount' => 0.00,
            'total' => 3000.00,
            'payment_method' => PaymentMethod::TRANSFER,
        ]);

        // Sale 5: Cancelled (Must be excluded)
        $this->createSale([
            'created_by' => $admin->id,
            'sale_date' => $now,
            'status' => SaleStatus::CANCELLED,
            'subtotal' => 999.00,
            'total' => 999.00,
            'payment_method' => PaymentMethod::UPI,
        ]);

        Livewire::actingAs($admin)
            ->test(SalesReport::class)
            ->assertViewHas('kpis', function (array $kpis) {
                // Gross: 1000 + 500 + 2000 + 3000 = 6500
                // Discounts: (100+50) + 0 + 200 + 0 = 350
                // Net: 850 + 500 + 1800 + 3000 = 6150
                // Total orders: 4
                // AOV: 6150 / 4 = 1537.50
                return $kpis['gross_sales'] == 6500.00
                    && $kpis['total_discounts'] == 350.00
                    && $kpis['net_sales'] == 6150.00
                    && $kpis['total_orders'] === 4
                    && $kpis['average_order_value'] == 1537.50;
            })
            ->assertViewHas('paymentMethods', function (array $methods) {
                return $methods['upi']['amount'] == 850.00
                    && $methods['upi']['count'] === 1
                    && $methods['cash']['amount'] == 500.00
                    && $methods['cash']['count'] === 1
                    && $methods['card']['amount'] == 1800.00
                    && $methods['card']['count'] === 1
                    && $methods['transfer']['amount'] == 3000.00
                    && $methods['transfer']['count'] === 1;
            })
            ->assertSee('6,150.00')
            ->assertSee('850.00')
            ->assertSee('1,800.00')
            ->assertSee('500.00')
            ->assertSee('3,000.00')
            ->assertSee('Gross Profit');
    }

    public function test_sales_report_calculates_cogs_and_profit_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['company_code' => 'C01', 'company_name' => 'Company 1']);
        $cat = Category::create(['name' => 'Cat', 'slug' => 'cat']);
        $unit = Unit::create(['name' => 'Unit', 'symbol' => 'u']);

        $product = Product::create([
            'name' => 'Serum Bottle',
            'sku' => 'SRM-01',
            'company_id' => $company->id,
            'category_id' => $cat->id,
            'unit_id' => $unit->id,
            'buying_price' => 200.00,
            'purchase_price' => 200.00,
            'selling_price' => 500.00,
            'mrp' => 500.00,
            'is_active' => true,
        ]);

        $sale = $this->createSale([
            'created_by' => $admin->id,
            'sale_date' => Carbon::now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1000.00,
            'total_discount' => 0.00,
            'total' => 1000.00,
            'payment_method' => PaymentMethod::UPI,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'cost_price' => 200.00,
            'unit_price' => 500.00,
            'subtotal' => 1000.00,
            'final_price' => 500.00,
        ]);

        Livewire::actingAs($admin)
            ->test(SalesReport::class)
            ->assertViewHas('kpis', function (array $kpis) {
                // Total Net: 1000.00
                // COGS: 2 * 200 = 400.00
                // Gross Profit: 1000 - 400 = 600.00
                // Margin: 60.0%
                return $kpis['net_sales'] == 1000.00
                    && $kpis['cogs'] == 400.00
                    && $kpis['gross_profit'] == 600.00
                    && $kpis['margin'] == 60.0;
            })
            ->assertSee('600.00')
            ->assertSee('60.0% margin');
    }

    public function test_date_filter_updates_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Sale yesterday
        $this->createSale([
            'created_by' => $admin->id,
            'sale_date' => Carbon::yesterday(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 200.00,
            'total' => 200.00,
            'payment_method' => PaymentMethod::CASH,
        ]);

        // Sale today
        $this->createSale([
            'created_by' => $admin->id,
            'sale_date' => Carbon::today(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 800.00,
            'total' => 800.00,
            'payment_method' => PaymentMethod::UPI,
        ]);

        // Filter: Today only
        Livewire::actingAs($admin)
            ->test(SalesReport::class)
            ->set('dateFilter', 'today')
            ->assertViewHas('kpis', function (array $kpis) {
                return $kpis['total_orders'] === 1 && $kpis['net_sales'] == 800.00;
            })
            // Switch to yesterday
            ->set('dateFilter', 'yesterday')
            ->assertViewHas('kpis', function (array $kpis) {
                return $kpis['total_orders'] === 1 && $kpis['net_sales'] == 200.00;
            });
    }

    public function test_export_csv_streams_sales_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->createSale([
            'invoice_number' => 'INV-EXP-TEST-001',
            'created_by' => $admin->id,
            'sale_date' => Carbon::now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1200.00,
            'total_discount' => 100.00,
            'coupon_discount' => 0.00,
            'total' => 1100.00,
            'payment_method' => PaymentMethod::UPI,
        ]);

        $component = Livewire::actingAs($admin)->test(SalesReport::class);

        $response = $component->call('exportCsv');
        $this->assertNotNull($response);
    }

    public function test_admin_can_download_and_stream_pdf_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->createSale([
            'invoice_number' => 'INV-PDF-001',
            'created_by' => $admin->id,
            'sale_date' => Carbon::now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 1500.00,
            'total' => 1500.00,
            'payment_method' => PaymentMethod::CARD,
        ]);

        // Download response
        $downloadResponse = $this->actingAs($admin)->get(route('reports.sales.pdf'));
        $downloadResponse->assertOk();
        $this->assertStringContainsString('application/pdf', $downloadResponse->headers->get('Content-Type'));

        // Stream response
        $streamResponse = $this->actingAs($admin)->get(route('reports.sales.pdf', ['stream' => 1]));
        $streamResponse->assertOk();
        $this->assertStringContainsString('application/pdf', $streamResponse->headers->get('Content-Type'));
    }
}
