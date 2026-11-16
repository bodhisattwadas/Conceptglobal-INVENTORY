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
}
