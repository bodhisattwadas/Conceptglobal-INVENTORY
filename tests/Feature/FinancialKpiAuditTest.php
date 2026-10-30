<?php

namespace Tests\Feature;

use App\DTOs\PurchaseData;
use App\DTOs\SaleData;
use App\Enums\CouponType;
use App\Enums\DatePeriod;
use App\Enums\FinanceCategoryType;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Livewire\Dashboard\Dashboard;
use App\Models\Category;
use App\Models\Company;
use App\Models\DiscountCoupon;
use App\Models\FinanceTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialKpiAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

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

        $this->supplier = Supplier::create([
            'name' => 'L\'Oreal Professional India',
            'contact_person' => 'Rajesh Sharma',
            'email' => 'orders@loreal-india.test',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $this->company = Company::create([
            'company_code' => 'LOR-01',
            'company_name' => 'L\'Oreal Paris',
        ]);

        DB::table('supplier_companies')->insert([
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->category = Category::create([
            'name' => 'Hair Care',
            'slug' => 'hair-care',
        ]);

        $this->unit = Unit::create([
            'name' => 'Bottle',
            'symbol' => 'btl',
        ]);
    }

    /**
     * Comprehensive end-to-end verification across the complete financial lifecycle:
     * PO creation -> PO receiving & cost propagation -> POS Sale execution -> KPI verification -> Livewire rendering.
     */
    public function test_complete_financial_calculation_pipeline_and_kpi_audit(): void
    {
        $saleDate = Carbon::parse('2026-10-30 11:30:00');
        Carbon::setTestNow($saleDate);

        // =========================================================================
        // SCENARIO 1: PO Creation with Vendor Discount
        // =========================================================================
        $product = Product::create([
            'name' => 'Mythic Hair Oil 100ml',
            'sku' => 'LOR-OIL-100',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'mrp' => 100.00,
            'selling_price' => 100.00,
            'purchase_price' => 0.00,
            'quantity' => 0,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $this->assertEquals(100.00, (float) $product->mrp);
        $this->assertEquals(0, $product->quantity);

        // Submit PO creation through the official endpoint
        $poPayload = [
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'location' => PurchaseLocation::SHOP->value,
            'purchase_date' => $saleDate->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'mrp' => 100.00,
                    'discount_percent' => 10.0,
                    'unit_price' => 90.00,
                ],
            ],
        ];

        $poResponse = $this->actingAs($this->admin)->post(route('purchases.store'), $poPayload);
        $poResponse->assertRedirect();

        $purchase = Purchase::query()->latest('id')->firstOrFail();
        $this->assertEquals(PurchaseStatus::DRAFT, $purchase->status);
        $this->assertEquals(900.00, (float) $purchase->total);

        $poItem = $purchase->items()->firstOrFail();
        $this->assertEquals($product->id, $poItem->product_id);
        $this->assertEquals(10, $poItem->quantity);
        $this->assertEquals(90.00, (float) $poItem->unit_price, 'Agreed buying unit price must be derived as 90.00');
        $this->assertEquals(900.00, (float) $poItem->subtotal, 'PO item subtotal must be exactly 900.00');

        // =========================================================================
        // SCENARIO 2: PO Receiving & Cost Price Propagation
        // =========================================================================
        $receivePayload = [
            'order_received_date' => $saleDate->toDateString(),
            'items' => [
                $poItem->id => [
                    'received_quantity' => 10,
                    'unit_price' => 90.00,
                    'product_mrp' => 100.00,
                    'selling_price' => 100.00,
                ],
            ],
        ];

        $receiveResponse = $this->actingAs($this->admin)
            ->patch(route('purchases.mark-received', $purchase), $receivePayload);
        $receiveResponse->assertRedirect();

        $purchase->refresh();
        $this->assertEquals(PurchaseStatus::RECEIVED, $purchase->status);

        $poItem->refresh();
        $this->assertEquals(10, $poItem->received_quantity);

        $product->refresh();
        $this->assertEquals(10, $product->quantity, 'Inventory quantity must increment by 10');
        $this->assertEquals(90.00, (float) $product->purchase_price, 'Product purchase_price must update to 90.00');
        $this->assertEquals(100.00, (float) $product->mrp, 'Product MRP must remain 100.00');

        // =========================================================================
        // SCENARIO 3: Complex Multi-Item POS Sale with Discounts, Promo Code, and Round-Off
        // =========================================================================
        $service = Service::create([
            'name' => 'Haircut & Styling Ritual',
            'code' => 'SRV-STYL-01',
            'cost' => 450.00,
            'is_active' => true,
        ]);

        $coupon = DiscountCoupon::create([
            'code' => 'PROMO40',
            'type' => CouponType::Fixed,
            'value' => 40,
            'is_active' => true,
            'starts_at' => $saleDate->copy()->subDays(1),
            'expires_at' => $saleDate->copy()->addDays(30),
        ]);

        $salePayload = [
            'sale_date' => $saleDate->toDateTimeString(),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'cash_received' => 600.00,
            'change' => 0.50,
            'round_off' => -0.50,
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'discount' => 5.00, // 5% of 100.00 MRP = 5.00 off per unit -> 95.00 unit price
                ],
                [
                    'service_id' => $service->id,
                    'quantity' => 1,
                    'unit_price' => 450.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $saleResponse = $this->actingAs($this->admin)
            ->postJson(route('sales.store'), $salePayload);
        $saleResponse->assertCreated();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertEquals(SaleStatus::COMPLETED, $sale->status);

        // Verify mathematical sale totals
        $this->assertEquals(599.50, (float) $sale->total, 'Sale total must be (190.00 + 450.00) - 40.00 - 0.50 = 599.50');
        $this->assertEquals(600.00, (float) $sale->cash_received, 'Cash received must be 600.00');
        $this->assertEquals(0.50, (float) $sale->change, 'Change returned must be 0.50');
        $this->assertEquals(-0.50, (float) $sale->round_off, 'Round-off adjustment must be recorded as -0.50');
        $this->assertEquals(40.00, (float) $sale->coupon_discount, 'Coupon discount must be recorded as 40.00');

        // Verify items line items and cost pricing
        $productSaleItem = $sale->items()->where('product_id', $product->id)->firstOrFail();
        $this->assertEquals(2, $productSaleItem->quantity);
        $this->assertEquals(100.00, (float) $productSaleItem->unit_price);
        $this->assertEquals(5.00, (float) $productSaleItem->discount);
        $this->assertEquals(95.00, (float) $productSaleItem->final_price);
        $this->assertEquals(190.00, (float) $productSaleItem->subtotal);
        $this->assertEquals(90.00, (float) $productSaleItem->cost_price, 'Product item cost_price must reflect buying cost 90.00');

        $serviceSaleItem = $sale->items()->where('service_id', $service->id)->firstOrFail();
        $this->assertNull($serviceSaleItem->product_id);
        $this->assertEquals(1, $serviceSaleItem->quantity);
        $this->assertEquals(450.00, (float) $serviceSaleItem->unit_price);
        $this->assertEquals(0.00, (float) $serviceSaleItem->discount);
        $this->assertEquals(450.00, (float) $serviceSaleItem->subtotal);
        $this->assertEquals(0.00, (float) $serviceSaleItem->cost_price, 'Service item cost_price must be exactly 0.00');

        // Inventory check: 10 received - 2 sold = 8 remaining
        $product->refresh();
        $this->assertEquals(8, $product->quantity);

        // Verify Finance Transaction
        $financeTxn = FinanceTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->firstOrFail();

        $this->assertEquals(599.50, (float) $financeTxn->amount, 'Finance Transaction income must equal 599.50');
        $this->assertEquals(FinanceCategoryType::Income, $financeTxn->category->type);

        // =========================================================================
        // SCENARIO 4: Dashboard KPI & Profit Calculation Verification
        // =========================================================================
        $statsService = app(DashboardStatsService::class);
        $salesStats = $statsService->getSalesStats($saleDate->copy()->startOfDay(), $saleDate->copy()->endOfDay(), 'custom');
        $cashFlowStats = $statsService->getCashFlowStats($saleDate->copy()->startOfDay(), $saleDate->copy()->endOfDay(), 'custom');

        $this->assertEquals(599.50, (float) $salesStats['total_revenue'], 'Total Revenue must be 599.50');
        $this->assertEquals(180.00, (float) $salesStats['cogs'], 'COGS must be 180.00 (2 units * 90.00 buying cost; service is 0.00)');
        $this->assertEquals(419.50, (float) $salesStats['gross_profit'], 'Gross Profit must be 419.50 (599.50 - 180.00)');
        $this->assertEquals(70.0, (float) $salesStats['margin'], 'Gross Profit Margin % must be round((419.50 / 599.50) * 100, 1) = 70.0%');

        $this->assertEquals(599.50, (float) $cashFlowStats['income'], 'Cash Flow Income must be 599.50');
        $this->assertEquals(0.00, (float) $cashFlowStats['expense'], 'Cash Flow Expense must be 0.00');
        $this->assertEquals(599.50, (float) $cashFlowStats['net_cash_flow'], 'Net Cash Flow must be 599.50');

        // =========================================================================
        // SCENARIO 5: Livewire Dashboard Component Rendering
        // =========================================================================
        Livewire::actingAs($this->admin)
            ->test(Dashboard::class)
            ->set('dateFilter', DatePeriod::TODAY->value)
            ->assertOk()
            ->assertSee(format_money(599.50))  // Total Sales / Revenue & Net Cash Flow
            ->assertSee(format_money(419.50))  // Gross Profit
            ->assertSee(format_money(180.00))  // COGS
            ->assertSee('70.0%')               // Margin %
            ->assertSee(format_money(599.50)); // Net Cash Flow

        Carbon::setTestNow(null);
    }

    /**
     * Isolated test for Scenario 1: PO Creation with Vendor Discount.
     */
    public function test_scenario_1_po_creation_derives_buying_unit_price_and_subtotal(): void
    {
        $product = Product::create([
            'name' => 'Hair Spa Cream 500g',
            'sku' => 'SPA-CRM-500',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'mrp' => 100.00,
            'selling_price' => 100.00,
            'purchase_price' => 0.00,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'location' => PurchaseLocation::PARLOUR->value,
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'mrp' => 100.00,
                    'discount_percent' => 10.0,
                    'unit_price' => 90.00,
                ],
            ],
        ]);

        $response->assertRedirect();

        $purchase = Purchase::latest('id')->firstOrFail();
        $this->assertEquals(900.00, (float) $purchase->total);

        $item = $purchase->items->first();
        $this->assertEquals(10, $item->quantity);
        $this->assertEquals(90.00, (float) $item->unit_price);
        $this->assertEquals(900.00, (float) $item->subtotal);
    }

    /**
     * Isolated test for Scenario 2: PO Receiving & Cost Price Propagation.
     */
    public function test_scenario_2_po_receiving_updates_inventory_and_cost_price(): void
    {
        $product = Product::create([
            'name' => 'Hair Spray 200ml',
            'sku' => 'SPRAY-200',
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'mrp' => 100.00,
            'selling_price' => 100.00,
            'purchase_price' => 0.00,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $purchaseService = app(PurchaseService::class);
        $purchaseData = PurchaseData::fromArray([
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'location' => PurchaseLocation::SHOP->value,
            'purchase_date' => now()->toDateString(),
            'status' => PurchaseStatus::DRAFT->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 90.00,
                    'selling_price' => 100.00,
                    'discount_percent' => 10.0,
                ],
            ],
        ]);

        $purchase = $purchaseService->createPurchase($purchaseData, $this->admin->id);
        $item = $purchase->items->first();

        // Mark as received
        $purchaseService->markAsReceived(
            $purchase,
            [$item->id => 10],
            [$item->id => [
                'order_received_date' => now()->toDateString(),
                'unit_price' => 90.00,
                'product_mrp' => 100.00,
            ]]
        );

        $product->refresh();
        $this->assertEquals(10, $product->quantity);
        $this->assertEquals(90.00, (float) $product->purchase_price);
        $this->assertEquals(100.00, (float) $product->mrp);
    }

    /**
     * Isolated test for Scenario 3: Complex Multi-Item POS Sale with Discounts, Promo Code, and Round-Off.
     */
    public function test_scenario_3_complex_sale_with_discounts_coupon_and_round_off(): void
    {
        $product = $this->createReceivedProduct('Shampoo 250ml', 'SHAMP-250', 100.00, 90.00, 10);

        $service = Service::create([
            'name' => 'Hair Therapy',
            'code' => 'SRV-THR-01',
            'cost' => 450.00,
            'is_active' => true,
        ]);

        $coupon = DiscountCoupon::create([
            'code' => 'SAVE40',
            'type' => CouponType::Fixed,
            'value' => 40,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $saleService = app(SaleService::class);
        $saleData = SaleData::fromArray([
            'sale_date' => now()->toDateTimeString(),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'created_by' => $this->admin->id,
            'cash_received' => 600.00,
            'change' => 0.50,
            'round_off' => -0.50,
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'discount' => 5.00,
                ],
                [
                    'service_id' => $service->id,
                    'quantity' => 1,
                    'unit_price' => 450.00,
                    'discount' => 0.00,
                ],
            ],
        ]);

        $sale = $saleService->createSale($saleData);

        // Verify calculations
        $this->assertEquals(599.50, (float) $sale->total);
        $this->assertEquals(600.00, (float) $sale->cash_received);
        $this->assertEquals(0.50, (float) $sale->change);

        $prodItem = $sale->items()->where('product_id', $product->id)->first();
        $this->assertEquals(90.00, (float) $prodItem->cost_price);

        $srvItem = $sale->items()->where('service_id', $service->id)->first();
        $this->assertEquals(0.00, (float) $srvItem->cost_price);

        $financeTxn = FinanceTransaction::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->first();
        $this->assertNotNull($financeTxn);
        $this->assertEquals(599.50, (float) $financeTxn->amount);
    }

    /**
     * Isolated test for Scenario 4 & 5: Dashboard KPI & Livewire Rendering.
     */
    public function test_scenarios_4_and_5_dashboard_kpis_and_livewire_rendering(): void
    {
        $now = Carbon::parse('2026-10-30 14:00:00');
        Carbon::setTestNow($now);

        $product = $this->createReceivedProduct('Hair Gel 100g', 'GEL-100', 100.00, 90.00, 10);

        $service = Service::create([
            'name' => 'Haircut',
            'code' => 'SRV-CUT-01',
            'cost' => 450.00,
            'is_active' => true,
        ]);

        $coupon = DiscountCoupon::create([
            'code' => 'DISC40',
            'type' => CouponType::Fixed,
            'value' => 40,
            'is_active' => true,
            'starts_at' => $now->copy()->subDay(),
            'expires_at' => $now->copy()->addMonth(),
        ]);

        $sale = app(SaleService::class)->createSale(SaleData::fromArray([
            'sale_date' => $now->toDateTimeString(),
            'payment_method' => PaymentMethod::CASH->value,
            'status' => SaleStatus::COMPLETED->value,
            'created_by' => $this->admin->id,
            'cash_received' => 600.00,
            'change' => 0.50,
            'round_off' => -0.50,
            'coupon_id' => $coupon->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'discount' => 5.00,
                ],
                [
                    'service_id' => $service->id,
                    'quantity' => 1,
                    'unit_price' => 450.00,
                    'discount' => 0.00,
                ],
            ],
        ]));

        $statsService = app(DashboardStatsService::class);
        $salesStats = $statsService->getSalesStats($now->copy()->startOfDay(), $now->copy()->endOfDay(), 'today');
        $cashFlowStats = $statsService->getCashFlowStats($now->copy()->startOfDay(), $now->copy()->endOfDay(), 'today');

        // Mathematical Assertions
        $this->assertEquals(599.50, (float) $salesStats['total_revenue']);
        $this->assertEquals(180.00, (float) $salesStats['cogs']);
        $this->assertEquals(419.50, (float) $salesStats['gross_profit']);
        $this->assertEquals(70.0, (float) $salesStats['margin']);
        $this->assertEquals(599.50, (float) $cashFlowStats['income']);
        $this->assertEquals(0.00, (float) $cashFlowStats['expense']);
        $this->assertEquals(599.50, (float) $cashFlowStats['net_cash_flow']);

        // Livewire Assertions
        Livewire::actingAs($this->admin)
            ->test(Dashboard::class)
            ->set('dateFilter', 'today')
            ->assertOk()
            ->assertSee(format_money(599.50))
            ->assertSee(format_money(419.50))
            ->assertSee(format_money(180.00))
            ->assertSee('70.0%')
            ->assertSee(format_money(599.50));

        Carbon::setTestNow(null);
    }

    private function createReceivedProduct(string $name, string $sku, float $mrp = 100.00, float $buyPrice = 90.00, int $quantity = 10): Product
    {
        $product = Product::create([
            'name' => $name,
            'sku' => $sku,
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'mrp' => $mrp,
            'selling_price' => $mrp,
            'purchase_price' => $buyPrice,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-'.strtoupper(uniqid()),
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'location' => PurchaseLocation::SHOP->value,
            'purchase_date' => now()->toDateString(),
            'status' => PurchaseStatus::DRAFT,
            'created_by' => $this->admin->id,
            'total' => $buyPrice * $quantity,
        ]);

        $item = $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $buyPrice,
            'subtotal' => $buyPrice * $quantity,
            'selling_price' => $mrp,
        ]);

        app(PurchaseService::class)->markAsReceived(
            $purchase,
            [$item->id => $quantity],
            [$item->id => [
                'order_received_date' => now()->toDateString(),
                'unit_price' => $buyPrice,
                'product_mrp' => $mrp,
            ]]
        );

        return $product->fresh();
    }
}
