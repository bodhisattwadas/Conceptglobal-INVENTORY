<?php

namespace Tests\Feature;

use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Enums\VendorInvoiceStatus;
use App\Livewire\Reports\VendorAgingReport;
use App\Livewire\VendorInvoices\VendorInvoiceTable;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\VendorAgingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use Tests\TestCase;

class VendorAgingReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected User $salesUser;

    protected Supplier $supplierAlpha;

    protected Supplier $supplierBeta;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);
        $this->manager = User::factory()->create(['role' => 'manager', 'name' => 'Manager User']);
        $this->salesUser = User::factory()->create(['role' => 'sales', 'name' => 'Sales User']);

        $this->supplierAlpha = Supplier::create([
            'name' => 'Alpha Suppliers Pvt Ltd',
            'contact_person' => 'Alice Alpha',
            'phone' => '9876500001',
            'email' => 'alice@alpha.com',
        ]);

        $this->supplierBeta = Supplier::create([
            'name' => 'Beta Logistics Ltd',
            'contact_person' => 'Bob Beta',
            'phone' => '9876500002',
            'email' => 'bob@beta.com',
        ]);

        $this->company = Company::create([
            'company_code' => 'BRD-GLOBAL',
            'company_name' => 'Global Brand',
            'short_name' => 'GB',
        ]);
    }

    protected function createPurchase(Supplier $supplier, string $poRef = 'PO-20261116-0001', float $total = 5000.00): Purchase
    {
        return Purchase::create([
            'invoice_number' => $poRef,
            'supplier_id' => $supplier->id,
            'company_id' => $this->company->id,
            'purchase_date' => now()->format('Y-m-d'),
            'location' => PurchaseLocation::SHOP,
            'total' => $total,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Test guests are redirected to login for report routes and exports.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.vendor-aging'))->assertRedirect(route('login'));
        $this->get(route('reports.vendor-aging.pdf'))->assertRedirect(route('login'));
        $this->get(route('reports.vendor-aging.csv'))->assertRedirect(route('login'));
        $this->get(route('reports.vendor-aging-pdf'))->assertRedirect(route('login'));
        $this->get(route('reports.vendor-aging-csv'))->assertRedirect(route('login'));
    }

    /**
     * Test unauthorized roles (sales) receive HTTP 403 Forbidden.
     */
    public function test_sales_role_receives_403_forbidden(): void
    {
        $this->actingAs($this->salesUser)->get(route('reports.vendor-aging'))->assertForbidden();
        $this->actingAs($this->salesUser)->get(route('reports.vendor-aging.pdf'))->assertForbidden();
        $this->actingAs($this->salesUser)->get(route('reports.vendor-aging.csv'))->assertForbidden();
        $this->actingAs($this->salesUser)->get(route('reports.vendor-aging-pdf'))->assertForbidden();
        $this->actingAs($this->salesUser)->get(route('reports.vendor-aging-csv'))->assertForbidden();
    }

    /**
     * Test admin and manager roles can access the aging report page.
     */
    public function test_admin_and_manager_can_access_aging_report(): void
    {
        $this->actingAs($this->admin)->get(route('reports.vendor-aging'))->assertOk()->assertSeeLivewire(VendorAgingReport::class);
        $this->actingAs($this->manager)->get(route('reports.vendor-aging'))->assertOk()->assertSeeLivewire(VendorAgingReport::class);
    }

    /**
     * Test navigation menus include Vendor Aging Report links.
     */
    public function test_navigation_menus_include_vendor_aging_report_link(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.vendor-aging'));
        $response->assertOk();
        $response->assertSee(route('reports.vendor-aging'));
        $response->assertSee('Vendor Aging Report');
    }

    /**
     * Test VendorInvoiceTable renders invoice numbers as clickable links to vendor-invoices.show,
     * including graceful fallback for missing numbers.
     */
    public function test_vendor_invoice_table_renders_clickable_links_with_fallback(): void
    {
        $purchase1 = $this->createPurchase($this->supplierAlpha, 'PO-REF-LINK-1', 4000.00);
        $invoiceWithNumber = VendorInvoice::create([
            'purchase_id' => $purchase1->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-REF-LINK-1',
            'invoice_number' => 'INV-CLICKABLE-101',
            'amount' => 4000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        $purchase2 = $this->createPurchase($this->supplierBeta, 'PO-REF-LINK-2', 2000.00);
        $invoiceWithoutNumber = VendorInvoice::create([
            'purchase_id' => $purchase2->id,
            'supplier_id' => $this->supplierBeta->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-REF-LINK-2',
            'invoice_number' => null,
            'amount' => 2000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        Livewire::actingAs($this->admin)
            ->test(VendorInvoiceTable::class)
            ->assertSeeHtml(route('vendor-invoices.show', $invoiceWithNumber))
            ->assertSeeHtml('INV-CLICKABLE-101')
            ->assertSeeHtml(route('vendor-invoices.show', $invoiceWithoutNumber));
    }

    /**
     * Test table export retains raw invoice numbers without HTML markup.
     */
    public function test_vendor_invoice_export_retains_raw_number_without_html(): void
    {
        $purchase1 = $this->createPurchase($this->supplierAlpha, 'PO-EXP-1', 1500.00);
        VendorInvoice::create([
            'purchase_id' => $purchase1->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-EXP-1',
            'invoice_number' => 'INV-RAW-777',
            'amount' => 1500.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        $component = Livewire::actingAs($this->admin)->test(VendorInvoiceTable::class)->instance();

        $currentHiddenStates = [];
        foreach ($component->columns() as $column) {
            $currentHiddenStates[$column->field] = $column->hidden;
        }

        $columnsWithHiddenState = array_map(function ($column) use ($currentHiddenStates) {
            $column->hidden = $currentHiddenStates[$column->field];

            return $column;
        }, $component->columns());

        $export = new Export;
        $prepared = $export->prepare($component->prepareToExport(), $columnsWithHiddenState, true);

        $this->assertNotEmpty($prepared['rows']);
        $matchingRow = collect($prepared['rows'])->firstWhere('PO Reference', 'PO-EXP-1');
        $this->assertNotNull($matchingRow);
        $this->assertSame('INV-RAW-777', $matchingRow['Invoice Number']);
        $this->assertStringNotContainsString('<', (string) $matchingRow['Invoice Number']);
        $this->assertStringNotContainsString('>', (string) $matchingRow['Invoice Number']);
    }

    /**
     * Test aging days calculation and exact bucket categorization:
     * 0-30 Days, 31-60 Days, 61-90 Days, 90+ Days.
     */
    public function test_aging_days_calculation_and_bucket_categorization(): void
    {
        // 1. Current (10 days old -> 0-30 bucket)
        $p1 = $this->createPurchase($this->supplierAlpha, 'PO-BUCKET-1', 1000.00);
        $inv1 = VendorInvoice::create([
            'purchase_id' => $p1->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-BUCKET-1',
            'invoice_number' => 'INV-B0-30',
            'invoice_date' => now()->subDays(10)->format('Y-m-d'),
            'amount' => 1000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        // 2. 31-60 Days (45 days old -> 31-60 bucket)
        $p2 = $this->createPurchase($this->supplierAlpha, 'PO-BUCKET-2', 2000.00);
        $inv2 = VendorInvoice::create([
            'purchase_id' => $p2->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-BUCKET-2',
            'invoice_number' => 'INV-B31-60',
            'invoice_date' => now()->subDays(45)->format('Y-m-d'),
            'amount' => 2000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        // 3. 61-90 Days (75 days old -> 61-90 bucket)
        $p3 = $this->createPurchase($this->supplierBeta, 'PO-BUCKET-3', 3000.00);
        $inv3 = VendorInvoice::create([
            'purchase_id' => $p3->id,
            'supplier_id' => $this->supplierBeta->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-BUCKET-3',
            'invoice_number' => 'INV-B61-90',
            'invoice_date' => now()->subDays(75)->format('Y-m-d'),
            'amount' => 3000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        // 4. 90+ Days (120 days old -> 90+ bucket)
        $p4 = $this->createPurchase($this->supplierBeta, 'PO-BUCKET-4', 4000.00);
        $inv4 = VendorInvoice::create([
            'purchase_id' => $p4->id,
            'supplier_id' => $this->supplierBeta->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-BUCKET-4',
            'invoice_number' => 'INV-B90-PLUS',
            'invoice_date' => now()->subDays(120)->format('Y-m-d'),
            'amount' => 4000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        $service = app(VendorAgingReportService::class);
        $reportData = $service->getReportData();

        $this->assertEquals(10, $inv1->fresh()->age_in_days);
        $this->assertEquals('0-30', $inv1->fresh()->aging_bucket);

        $this->assertEquals(45, $inv2->fresh()->age_in_days);
        $this->assertEquals('31-60', $inv2->fresh()->aging_bucket);

        $this->assertEquals(75, $inv3->fresh()->age_in_days);
        $this->assertEquals('61-90', $inv3->fresh()->aging_bucket);

        $this->assertEquals(120, $inv4->fresh()->age_in_days);
        $this->assertEquals('90+', $inv4->fresh()->aging_bucket);

        $kpis = $reportData['kpis'];
        $this->assertEquals(10000.00, $kpis['total_due']);
        $this->assertEquals(1000.00, $kpis['bucket_0_30_due']);
        $this->assertEquals(2000.00, $kpis['bucket_31_60_due']);
        $this->assertEquals(3000.00, $kpis['bucket_61_90_due']);
        $this->assertEquals(4000.00, $kpis['bucket_90_plus_due']);
        $this->assertEquals(4, $kpis['invoices_count']);
        $this->assertEquals(2, $kpis['vendors_count']);
    }

    /**
     * Test date fallback hierarchy: invoice_date -> order_received_date -> created_at.
     */
    public function test_date_fallback_hierarchy(): void
    {
        // 1. Has invoice_date: must use invoice_date (12 days ago) regardless of other dates
        $p1 = $this->createPurchase($this->supplierAlpha, 'PO-HIER-1', 1000.00);
        $inv1 = VendorInvoice::create([
            'purchase_id' => $p1->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-HIER-1',
            'invoice_number' => 'INV-HIER-1',
            'invoice_date' => now()->subDays(12)->format('Y-m-d'),
            'order_received_date' => now()->subDays(80)->format('Y-m-d'),
            'amount' => 1000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);
        $inv1->created_at = now()->subDays(100);
        $inv1->save();

        // 2. Missing invoice_date: falls back to order_received_date (40 days ago)
        $p2 = $this->createPurchase($this->supplierAlpha, 'PO-HIER-2', 1200.00);
        $inv2 = VendorInvoice::create([
            'purchase_id' => $p2->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-HIER-2',
            'invoice_number' => 'INV-HIER-2',
            'invoice_date' => null,
            'order_received_date' => now()->subDays(40)->format('Y-m-d'),
            'amount' => 1200.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);
        $inv2->created_at = now()->subDays(95);
        $inv2->save();

        // 3. Missing both: falls back to created_at (70 days ago)
        $p3 = $this->createPurchase($this->supplierBeta, 'PO-HIER-3', 1500.00);
        $inv3 = VendorInvoice::create([
            'purchase_id' => $p3->id,
            'supplier_id' => $this->supplierBeta->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-HIER-3',
            'invoice_number' => 'INV-HIER-3',
            'invoice_date' => null,
            'order_received_date' => null,
            'amount' => 1500.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);
        $inv3->created_at = now()->subDays(70);
        $inv3->save();

        $service = app(VendorAgingReportService::class);
        $reportData = $service->getReportData();
        $invoices = $reportData['invoices'];

        $item1 = $invoices->firstWhere('id', $inv1->id);
        $this->assertEquals(12, $item1->age_in_days);
        $this->assertEquals('0-30', $item1->aging_bucket);

        $item2 = $invoices->firstWhere('id', $inv2->id);
        $this->assertEquals(40, $item2->age_in_days);
        $this->assertEquals('31-60', $item2->aging_bucket);

        $item3 = $invoices->firstWhere('id', $inv3->id);
        $this->assertEquals(70, $item3->age_in_days);
        $this->assertEquals('61-90', $item3->aging_bucket);
    }

    /**
     * Test exclusion of fully paid invoices:
     * status = 'paid' or due amount <= 0.00.
     */
    public function test_fully_paid_invoices_are_strictly_excluded(): void
    {
        // Fully paid invoice with status 'paid'
        $p1 = $this->createPurchase($this->supplierAlpha, 'PO-PAID-1', 5000.00);
        $paidInvoice = VendorInvoice::create([
            'purchase_id' => $p1->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-PAID-1',
            'invoice_number' => 'INV-EXCL-PAID',
            'invoice_date' => now()->subDays(50)->format('Y-m-d'),
            'amount' => 5000.00,
            'paid_amount' => 5000.00,
            'status' => VendorInvoiceStatus::PAID,
        ]);

        // Invoice with due balance 0.00 but marked unpaid
        $p2 = $this->createPurchase($this->supplierAlpha, 'PO-PAID-2', 3000.00);
        $zeroDueInvoice = VendorInvoice::create([
            'purchase_id' => $p2->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-PAID-2',
            'invoice_number' => 'INV-EXCL-ZERODUE',
            'invoice_date' => now()->subDays(50)->format('Y-m-d'),
            'amount' => 3000.00,
            'paid_amount' => 3000.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        // Invoice with status paid even if amount > paid_amount
        $p3 = $this->createPurchase($this->supplierAlpha, 'PO-PAID-3', 4000.00);
        $statusPaidInvoice = VendorInvoice::create([
            'purchase_id' => $p3->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-PAID-3',
            'invoice_number' => 'INV-EXCL-STATUSPAID',
            'invoice_date' => now()->subDays(50)->format('Y-m-d'),
            'amount' => 4000.00,
            'paid_amount' => 1000.00,
            'status' => VendorInvoiceStatus::PAID,
        ]);

        // Open unpaid invoice (must be included)
        $p4 = $this->createPurchase($this->supplierAlpha, 'PO-OPEN-1', 2500.00);
        $openInvoice = VendorInvoice::create([
            'purchase_id' => $p4->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-OPEN-1',
            'invoice_number' => 'INV-INCL-OPEN',
            'invoice_date' => now()->subDays(15)->format('Y-m-d'),
            'amount' => 2500.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        $service = app(VendorAgingReportService::class);
        $reportData = $service->getReportData();
        $invoices = $reportData['invoices'];

        $this->assertCount(1, $invoices);
        $this->assertEquals($openInvoice->id, $invoices->first()->id);
        $this->assertEquals(2500.00, $reportData['kpis']['total_due']);
    }

    /**
     * Test partially paid invoices calculate exact net due balance (amount - paid_amount).
     */
    public function test_partially_paid_invoices_calculate_net_due_balance(): void
    {
        $p = $this->createPurchase($this->supplierBeta, 'PO-PARTIAL-1', 8000.00);
        $partialInvoice = VendorInvoice::create([
            'purchase_id' => $p->id,
            'supplier_id' => $this->supplierBeta->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-PARTIAL-1',
            'invoice_number' => 'INV-PARTIAL-99',
            'invoice_date' => now()->subDays(35)->format('Y-m-d'),
            'amount' => 8000.00,
            'paid_amount' => 3000.00,
            'status' => VendorInvoiceStatus::PARTIALLY_PAID,
        ]);

        $service = app(VendorAgingReportService::class);
        $reportData = $service->getReportData();
        $invoices = $reportData['invoices'];

        $this->assertCount(1, $invoices);
        $item = $invoices->first();
        $this->assertEquals(5000.00, $item->due_amount);
        $this->assertEquals(5000.00, $reportData['kpis']['total_due']);
        $this->assertEquals(5000.00, $reportData['kpis']['bucket_31_60_due']);
    }

    /**
     * Test vendor breakdown correctly aggregates bucket totals per vendor.
     */
    public function test_vendor_breakdown_aggregations(): void
    {
        // Vendor Alpha: invoice in 0-30 (1000) and invoice in 31-60 (2000) -> total 3000
        $p1 = $this->createPurchase($this->supplierAlpha, 'PO-VEND-1', 1000.00);
        VendorInvoice::create([
            'purchase_id' => $p1->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-VEND-1',
            'invoice_number' => 'INV-ALPHA-1',
            'invoice_date' => now()->subDays(5)->format('Y-m-d'),
            'amount' => 1000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        $p2 = $this->createPurchase($this->supplierAlpha, 'PO-VEND-2', 2000.00);
        VendorInvoice::create([
            'purchase_id' => $p2->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-VEND-2',
            'invoice_number' => 'INV-ALPHA-2',
            'invoice_date' => now()->subDays(45)->format('Y-m-d'),
            'amount' => 2000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        // Vendor Beta: invoice in 90+ (4500) -> total 4500
        $p3 = $this->createPurchase($this->supplierBeta, 'PO-VEND-3', 4500.00);
        VendorInvoice::create([
            'purchase_id' => $p3->id,
            'supplier_id' => $this->supplierBeta->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-VEND-3',
            'invoice_number' => 'INV-BETA-1',
            'invoice_date' => now()->subDays(100)->format('Y-m-d'),
            'amount' => 4500.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        $service = app(VendorAgingReportService::class);
        $reportData = $service->getReportData();
        $breakdown = $reportData['vendor_breakdown'];

        $this->assertCount(2, $breakdown);

        // Beta has higher total due (4500), so sorted first
        $betaRow = $breakdown->firstWhere('supplier_id', $this->supplierBeta->id);
        $this->assertNotNull($betaRow);
        $this->assertEquals(4500.00, $betaRow['total_due']);
        $this->assertEquals(4500.00, $betaRow['bucket_90_plus']);
        $this->assertEquals(1, $betaRow['invoice_count']);

        $alphaRow = $breakdown->firstWhere('supplier_id', $this->supplierAlpha->id);
        $this->assertNotNull($alphaRow);
        $this->assertEquals(3000.00, $alphaRow['total_due']);
        $this->assertEquals(1000.00, $alphaRow['bucket_0_30']);
        $this->assertEquals(2000.00, $alphaRow['bucket_31_60']);
        $this->assertEquals(2, $alphaRow['invoice_count']);
    }

    /**
     * Test interactive Livewire filtering by vendor, bucket, and search query.
     */
    public function test_livewire_interactive_filtering_and_sorting(): void
    {
        $p1 = $this->createPurchase($this->supplierAlpha, 'PO-LIVE-1', 1000.00);
        VendorInvoice::create([
            'purchase_id' => $p1->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-LIVE-1',
            'invoice_number' => 'INV-ALPHA-100',
            'invoice_date' => now()->subDays(10)->format('Y-m-d'),
            'amount' => 1000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        $p2 = $this->createPurchase($this->supplierBeta, 'PO-LIVE-2', 5000.00);
        VendorInvoice::create([
            'purchase_id' => $p2->id,
            'supplier_id' => $this->supplierBeta->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-LIVE-2',
            'invoice_number' => 'INV-BETA-200',
            'invoice_date' => now()->subDays(50)->format('Y-m-d'),
            'amount' => 5000.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        // Filter by supplier Alpha
        Livewire::actingAs($this->admin)
            ->test(VendorAgingReport::class)
            ->assertSee('INV-ALPHA-100')
            ->assertSee('INV-BETA-200')
            ->set('supplierId', (string) $this->supplierAlpha->id)
            ->assertSee('INV-ALPHA-100')
            ->assertDontSee('INV-BETA-200')
            ->set('supplierId', 'all')
            ->assertSee('INV-BETA-200')
            // Filter by aging bucket 31-60
            ->set('bucket', '31-60')
            ->assertSee('INV-BETA-200')
            ->assertDontSee('INV-ALPHA-100')
            // Filter by search keyword
            ->set('bucket', 'all')
            ->set('search', 'ALPHA')
            ->assertSee('INV-ALPHA-100')
            ->assertDontSee('INV-BETA-200')
            // Reset filters
            ->call('resetFilters')
            ->assertSee('INV-ALPHA-100')
            ->assertSee('INV-BETA-200');
    }

    /**
     * Test PDF export endpoint returns valid A4 landscape PDF.
     */
    public function test_pdf_export_endpoint_returns_valid_a4_landscape_pdf(): void
    {
        $p = $this->createPurchase($this->supplierAlpha, 'PO-PDF-1', 2500.00);
        VendorInvoice::create([
            'purchase_id' => $p->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-PDF-1',
            'invoice_number' => 'INV-PDF-1',
            'invoice_date' => now()->subDays(20)->format('Y-m-d'),
            'amount' => 2500.00,
            'paid_amount' => 500.00,
            'status' => VendorInvoiceStatus::PARTIALLY_PAID,
        ]);

        // Route: reports.vendor-aging.pdf
        $response1 = $this->actingAs($this->admin)->get(route('reports.vendor-aging.pdf'));
        $response1->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response1->headers->get('content-type'));

        // Route alias: reports.vendor-aging-pdf
        $response2 = $this->actingAs($this->admin)->get(route('reports.vendor-aging-pdf'));
        $response2->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response2->headers->get('content-type'));
    }

    /**
     * Test CSV export endpoint streams valid CSV with UTF-8 BOM, structured headers, and clean rows.
     */
    public function test_csv_export_endpoint_streams_valid_csv(): void
    {
        $p = $this->createPurchase($this->supplierAlpha, 'PO-CSV-1', 3500.00);
        VendorInvoice::create([
            'purchase_id' => $p->id,
            'supplier_id' => $this->supplierAlpha->id,
            'company_id' => $this->company->id,
            'po_reference' => 'PO-CSV-1',
            'invoice_number' => 'INV-CSV-999',
            'invoice_date' => now()->subDays(40)->format('Y-m-d'),
            'amount' => 3500.00,
            'paid_amount' => 1000.00,
            'status' => VendorInvoiceStatus::PARTIALLY_PAID,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.vendor-aging.csv'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        // Capture streamed content
        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        // Check UTF-8 BOM
        $bom = chr(0xEF).chr(0xBB).chr(0xBF);
        $this->assertStringStartsWith($bom, $csvContent);

        // Check header sections
        $this->assertStringContainsString('ACCOUNTS PAYABLE VENDOR AGING REPORT', $csvContent);
        $this->assertStringContainsString('SUMMARY KPIS', $csvContent);
        $this->assertStringContainsString('VENDOR BREAKDOWN', $csvContent);
        $this->assertStringContainsString('DETAILED INVOICE LISTING', $csvContent);

        // Check data row
        $this->assertStringContainsString('INV-CSV-999', $csvContent);
        $this->assertStringContainsString('PO-CSV-1', $csvContent);
        $this->assertStringContainsString('Alpha Suppliers Pvt Ltd', $csvContent);
        $this->assertStringContainsString('2500.00', $csvContent);

        // Verify no HTML tags in CSV
        $this->assertStringNotContainsString('<div>', $csvContent);
        $this->assertStringNotContainsString('<span>', $csvContent);
        $this->assertStringNotContainsString('<a href', $csvContent);
    }
}
