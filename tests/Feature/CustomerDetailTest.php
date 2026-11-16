<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Livewire\Customers\CustomerDetail;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerDetailTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $brand;

    protected Category $category;

    protected Unit $unit;

    protected Supplier $supplier;

    protected Product $product;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => UserRole::Admin]);
        $this->brand = Company::create(['company_name' => 'Lotus Herbals', 'short_name' => 'Lotus', 'company_code' => 'LOTUS']);
        $this->category = Category::create(['name' => 'Skin Care', 'slug' => 'skin-care']);
        $this->unit = Unit::create(['name' => 'Pieces', 'symbol' => 'pcs']);
        $this->supplier = Supplier::factory()->create();

        $this->product = Product::create([
            'name' => 'Lotus Face Cream 50g',
            'sku' => 'LOTUS-FC-50',
            'category_id' => $this->category->id,
            'company_id' => $this->brand->id,
            'unit_id' => $this->unit->id,
            'buy_price' => 100,
            'sell_price' => 200,
            'barcode' => '8901234567890',
        ]);

        $this->customer = Customer::create([
            'name' => 'Aditi Sharma',
            'phone' => '+91 98765 43210',
            'email' => 'aditi@example.com',
            'address' => '12 Park Street, Kolkata',
            'notes' => 'Prefers Lotus products',
        ]);
    }

    protected function createSaleWithBatch(string $invoiceNumber, float $unitPrice, int $qty, string $batchNo = 'LOT-001'): Sale
    {
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->subDays(5),
            'status' => 'received',
            'total_amount' => 1000,
            'created_by' => $this->user->id,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'buy_price' => 100,
            'batch_number' => $batchNo,
            'subtotal' => 1000,
        ]);

        $sale = Sale::create([
            'customer_id' => $this->customer->id,
            'invoice_number' => $invoiceNumber,
            'sale_date' => now()->subDay(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => $unitPrice * $qty,
            'total_discount' => 0,
            'tax' => 0,
            'total' => $unitPrice * $qty,
            'created_by' => $this->user->id,
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'item_name' => $this->product->name,
            'quantity' => $qty,
            'cost_price' => 100,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'final_price' => $unitPrice,
            'subtotal' => $unitPrice * $qty,
        ]);

        InventoryMovement::create([
            'product_id' => $this->product->id,
            'purchase_item_id' => $purchaseItem->id,
            'sale_item_id' => $saleItem->id,
            'quantity' => -$qty,
            'type' => 'sale',
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);

        return $sale;
    }

    public function test_customer_service_retrieves_transaction_history_and_metrics(): void
    {
        $this->createSaleWithBatch('INV-261116-001', 200.0, 2, 'BATCH-A1');
        $this->createSaleWithBatch('INV-261116-002', 300.0, 1, 'BATCH-B2');

        $customerService = app(CustomerService::class);
        $data = $customerService->getCustomerTransactionsData($this->customer);

        $this->assertEquals(2, $data['metrics']['sales_count']);
        $this->assertEquals(700.0, $data['metrics']['total_spent']);
        $this->assertEquals(3, $data['metrics']['total_items_count']);
        $this->assertEquals(350.0, $data['metrics']['aov']);
        $this->assertCount(2, $data['sales']);

        $firstSale = $data['sales']->first();
        $this->assertEquals('INV-261116-002', $firstSale['invoice_number']);
        $this->assertEquals('BATCH-B2', $firstSale['items'][0]['batch_number']);
        $this->assertEquals('Lotus Herbals', $firstSale['items'][0]['brand_name']);
    }

    public function test_customer_detail_livewire_component_renders_and_toggles_accordion(): void
    {
        $sale = $this->createSaleWithBatch('INV-261116-003', 250.0, 2, 'BATCH-C3');

        Livewire::actingAs($this->user)
            ->test(CustomerDetail::class)
            ->call('show', $this->customer)
            ->assertSee('Aditi Sharma')
            ->assertSee('INV-261116-003')
            ->assertSee('Lotus Face Cream 50g')
            ->assertSee('BATCH-C3')
            ->call('toggleSaleAccordion', $sale->id)
            ->assertSet('expandedSales', []);
    }

    public function test_customer_show_route_renders_full_page(): void
    {
        $this->createSaleWithBatch('INV-261116-004', 150.0, 1, 'BATCH-D4');

        $response = $this->actingAs($this->user)->get(route('customers.show', $this->customer));

        $response->assertStatus(200)
            ->assertSee('Aditi Sharma')
            ->assertSee('INV-261116-004')
            ->assertSee('Lotus Face Cream 50g');
    }

    public function test_ajax_customer_phone_search_finds_customer_and_sales_history(): void
    {
        $this->createSaleWithBatch('INV-261116-005', 400.0, 1, 'BATCH-E5');

        $response = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'phone' => '9876543210',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('found', true)
            ->assertJsonPath('customer.name', 'Aditi Sharma')
            ->assertJsonCount(1, 'sales');
    }

    public function test_search_preview_and_customer_modal_have_consistent_sales_count_and_total_including_services_and_custom_items(): void
    {
        $customer = Customer::create([
            'name' => 'Saheli Banerjee',
            'phone' => '+91 98364 05829',
            'email' => 'saheli@example.com',
        ]);

        $service = Service::create([
            'name' => 'Hair Spa Therapy',
            'code' => 'SRV-HSP-01',
            'cost' => 500,
            'is_active' => true,
        ]);

        // Sale 1: Product purchase (400.00)
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->subDays(5),
            'status' => 'received',
            'total_amount' => 1000,
            'created_by' => $this->user->id,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'buy_price' => 100,
            'batch_number' => 'LOTUS-BATCH-1',
            'subtotal' => 1000,
        ]);

        $sale1 = Sale::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SB-001',
            'sale_date' => now()->subDays(3),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 400,
            'total_discount' => 0,
            'tax' => 0,
            'total' => 400,
            'created_by' => $this->user->id,
        ]);

        $saleItem1 = SaleItem::create([
            'sale_id' => $sale1->id,
            'product_id' => $this->product->id,
            'item_name' => $this->product->name,
            'quantity' => 2,
            'cost_price' => 100,
            'unit_price' => 200,
            'discount' => 0,
            'final_price' => 200,
            'subtotal' => 400,
        ]);

        InventoryMovement::create([
            'product_id' => $this->product->id,
            'purchase_item_id' => $purchaseItem->id,
            'sale_item_id' => $saleItem1->id,
            'quantity' => -2,
            'type' => 'sale',
            'reference_type' => Sale::class,
            'reference_id' => $sale1->id,
        ]);

        // Sale 2: Service-only invoice (500.00) - product_id is NULL
        $sale2 = Sale::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SB-002',
            'sale_date' => now()->subDays(2),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CARD,
            'subtotal' => 500,
            'total_discount' => 0,
            'tax' => 0,
            'total' => 500,
            'created_by' => $this->user->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale2->id,
            'service_id' => $service->id,
            'product_id' => null,
            'item_name' => $service->name,
            'quantity' => 1,
            'cost_price' => 0,
            'unit_price' => 500,
            'discount' => 0,
            'final_price' => 500,
            'subtotal' => 500,
        ]);

        // Sale 3: Custom/manual item invoice (150.00) - product_id and service_id are NULL
        $sale3 = Sale::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SB-003',
            'sale_date' => now()->subDay(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::UPI,
            'subtotal' => 150,
            'total_discount' => 0,
            'tax' => 0,
            'total' => 150,
            'created_by' => $this->user->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale3->id,
            'product_id' => null,
            'service_id' => null,
            'custom_name' => 'Custom Facial Add-on',
            'is_manual' => true,
            'quantity' => 1,
            'cost_price' => 0,
            'unit_price' => 150,
            'discount' => 0,
            'final_price' => 150,
            'subtotal' => 150,
        ]);

        // Verify CustomerService (modal source of truth)
        $customerService = app(CustomerService::class);
        $modalData = $customerService->getCustomerTransactionsData($customer);

        $this->assertEquals(3, $modalData['metrics']['sales_count'], 'Modal should count all 3 bills');
        $this->assertEquals(1050.0, $modalData['metrics']['total_spent'], 'Modal total should sum all 3 bills');

        // Verify Search preview banner endpoint (POST ajax.customers.sales-history)
        $response = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'query' => '98364 05829',
            'phone' => '98364 05829',
            'full_history' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('found', true)
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('sales_count', 3)
            ->assertJsonPath('total_spent', fn ($val) => (float) $val === 1050.0)
            ->assertJsonCount(3, 'sales');

        $sales = collect($response->json('sales'));
        $totalSum = $sales->sum(fn ($s) => (float) $s['total']);
        $this->assertEquals(1050.0, $totalSum, 'Search preview calculated total must match 1050.00 exactly');
        $this->assertEquals($modalData['metrics']['sales_count'], $sales->count(), 'Search preview bill count must equal modal count');
        $this->assertEquals($modalData['metrics']['total_spent'], $totalSum, 'Search preview total spend must equal modal total spend');

        // Verify non-product items are included in mapped sales
        $serviceSale = $sales->firstWhere('invoice_number', 'INV-SB-002');
        $this->assertNotNull($serviceSale);
        $this->assertCount(1, $serviceSale['items']);
        $this->assertEquals('Hair Spa Therapy', $serviceSale['items'][0]['product_name']);
        $this->assertFalse($serviceSale['items'][0]['is_product']);
        $this->assertTrue($serviceSale['items'][0]['is_service']);

        $customSale = $sales->firstWhere('invoice_number', 'INV-SB-003');
        $this->assertNotNull($customSale);
        $this->assertCount(1, $customSale['items']);
        $this->assertEquals('Custom Facial Add-on', $customSale['items'][0]['product_name']);
        $this->assertFalse($customSale['items'][0]['is_product']);

        // Also verify searching by invoice number with full_history returns full customer sales history
        $invoiceResponse = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'query' => 'INV-SB-001',
            'full_history' => true,
        ]);

        $invoiceResponse->assertStatus(200)
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('sales_count', 3)
            ->assertJsonPath('total_spent', fn ($val) => (float) $val === 1050.0)
            ->assertJsonCount(3, 'sales');
    }

    public function test_customer_search_handles_various_phone_number_formats_and_names(): void
    {
        $customer = Customer::create([
            'name' => 'Saheli Banerjee',
            'phone' => '+91 98364 05829',
            'email' => 'saheli.format@example.com',
        ]);

        Sale::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-FMT-001',
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 500,
            'total_discount' => 0,
            'tax' => 0,
            'total' => 500,
            'created_by' => $this->user->id,
        ]);

        $queries = [
            '98364 05829',
            '9836405829',
            '+91 98364 05829',
            '+919836405829',
            '09836405829',
            '98364-05829',
            '+91-98364-05829',
            'Saheli Banerjee',
            'Saheli',
        ];

        foreach ($queries as $query) {
            $response = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
                'query' => $query,
                'full_history' => true,
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('found', true, "Failed for query: {$query}")
                ->assertJsonPath('customer.id', $customer->id, "Customer ID mismatch for query: {$query}")
                ->assertJsonPath('sales_count', 1)
                ->assertJsonPath('total_spent', fn ($val) => (float) $val === 500.0);
        }
    }

    public function test_search_preview_and_modal_exclude_cancelled_sales_from_sales_count_and_total_spent(): void
    {
        $customer = Customer::create([
            'name' => 'Rahul Sen',
            'phone' => '+91 98765 11111',
        ]);

        // Completed Sale 1 (300.00)
        Sale::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-RS-001',
            'sale_date' => now()->subDays(2),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 300,
            'total_discount' => 0,
            'tax' => 0,
            'total' => 300,
            'created_by' => $this->user->id,
        ]);

        // Completed Sale 2 (200.00)
        Sale::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-RS-002',
            'sale_date' => now()->subDay(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::UPI,
            'subtotal' => 200,
            'total_discount' => 0,
            'tax' => 0,
            'total' => 200,
            'created_by' => $this->user->id,
        ]);

        // Cancelled Sale (400.00) - should NOT count towards completed totals or bills
        Sale::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-RS-003',
            'sale_date' => now(),
            'status' => SaleStatus::CANCELLED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 400,
            'total_discount' => 0,
            'tax' => 0,
            'total' => 400,
            'created_by' => $this->user->id,
        ]);

        $customerService = app(CustomerService::class);
        $modalData = $customerService->getCustomerTransactionsData($customer);

        $this->assertEquals(2, $modalData['metrics']['sales_count'], 'Modal must only count completed sales');
        $this->assertEquals(500.0, $modalData['metrics']['total_spent'], 'Modal must only sum completed sales');

        $response = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'query' => '98765 11111',
            'full_history' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('sales_count', 2)
            ->assertJsonPath('total_spent', fn ($val) => (float) $val === 500.0)
            ->assertJsonCount(2, 'sales');

        $this->assertEquals($modalData['metrics']['sales_count'], $response->json('sales_count'));
        $this->assertEquals($modalData['metrics']['total_spent'], (float) $response->json('total_spent'));
    }

    public function test_pos_return_fetches_single_invoice_without_full_history_while_customer_search_fetches_all(): void
    {
        $customer = Customer::create([
            'name' => 'Maya Roy',
            'phone' => '+91 98765 22222',
        ]);

        Sale::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-RET-001',
            'sale_date' => now()->subDay(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 400,
            'total_discount' => 0,
            'tax' => 0,
            'total' => 400,
            'created_by' => $this->user->id,
        ]);

        Sale::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-RET-002',
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 600,
            'total_discount' => 0,
            'tax' => 0,
            'total' => 600,
            'created_by' => $this->user->id,
        ]);

        // POS return lookup without full_history: only returns matching invoice
        $posResponse = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'query' => 'INV-RET-001',
        ]);

        $posResponse->assertStatus(200)
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonCount(1, 'sales')
            ->assertJsonPath('sales.0.invoice_number', 'INV-RET-001');

        // Customer index lookup with full_history: returns all customer invoices
        $customerResponse = $this->actingAs($this->user)->postJson(route('ajax.customers.sales-history'), [
            'query' => 'INV-RET-001',
            'full_history' => true,
        ]);

        $customerResponse->assertStatus(200)
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonCount(2, 'sales')
            ->assertJsonPath('sales_count', 2)
            ->assertJsonPath('total_spent', fn ($val) => (float) $val === 1000.0);
    }
}
