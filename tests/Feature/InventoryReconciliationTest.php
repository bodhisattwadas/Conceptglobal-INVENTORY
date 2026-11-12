<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Livewire\Inventory\InventoryReconciliationModal;
use App\Livewire\Inventory\InventoryReconciliationTable;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryReconciliation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $salesUser;

    protected Category $category;

    protected Unit $unit;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->salesUser = User::factory()->create(['role' => 'sales']);

        $this->category = Category::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
        $this->unit = Unit::create(['name' => 'Piece', 'symbol' => 'pc']);
        $this->company = Company::create([
            'company_code' => 'COMP-LOR-01',
            'company_name' => 'L\'Oreal Professional',
            'short_name' => 'L\'Oreal',
        ]);
    }

    public function test_cashier_can_make_sale_with_manual_unlisted_product_at_pos(): void
    {
        $payload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 600.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Uncataloged Salon Shampoo 250ml',
                    'barcode' => '890123456789',
                    'quantity' => 2,
                    'unit_price' => 350.00,
                    'discount' => 50.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->salesUser)
            ->postJson(route('sales.store'), $payload);

        $response->assertStatus(201);

        $sale = Sale::latest()->first();
        $this->assertNotNull($sale);
        $this->assertEquals(600.00, (float) $sale->total);

        // Verify SaleItem has manual entry details
        $item = $sale->items()->first();
        $this->assertNotNull($item);
        $this->assertTrue($item->isManual());
        $this->assertNull($item->product_id);
        $this->assertEquals('Uncataloged Salon Shampoo 250ml', $item->custom_name);
        $this->assertEquals('890123456789', $item->barcode);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(350.00, (float) $item->unit_price);
        $this->assertEquals(50.00, (float) $item->discount);
        $this->assertEquals('Uncataloged Salon Shampoo 250ml', $item->item_name);

        // Verify InventoryReconciliation record was automatically created
        $recon = InventoryReconciliation::where('sale_item_id', $item->id)->first();
        $this->assertNotNull($recon);
        $this->assertEquals('pending', $recon->status);
        $this->assertEquals('Uncataloged Salon Shampoo 250ml', $recon->item_name);
        $this->assertEquals('890123456789', $recon->barcode);
        $this->assertEquals(2, $recon->quantity);
        $this->assertEquals(350.00, (float) $recon->unit_price);
        $this->assertEquals(600.00, (float) $recon->subtotal);
        $this->assertEquals($this->salesUser->id, $recon->created_by);
        $this->assertStringStartsWith('REC.', $recon->reconciliation_number);
    }

    public function test_admin_can_link_existing_product_master_and_reconcile(): void
    {
        // 1. Create a product in master with stock
        $product = Product::create([
            'name' => 'L\'Oreal Mythic Oil 100ml',
            'sku' => 'SKU-MYTH-100',
            'product_code' => '890987654321',
            'selling_price' => 850.00,
            'purchase_price' => 500.00,
            'quantity' => 10,
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'location' => PurchaseLocation::SHOP,
        ]);
        InventoryStock::create([
            'product_id' => $product->id,
            'location' => PurchaseLocation::SHOP,
            'quantity' => 10,
        ]);

        // 2. Make a manual sale at POS
        $payload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 1700.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Mythic Oil (Manual)',
                    'barcode' => '890987654321',
                    'quantity' => 2,
                    'unit_price' => 850.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $res = $this->actingAs($this->salesUser)->postJson(route('sales.store'), $payload);
        $res->assertStatus(201);
        $recon = InventoryReconciliation::latest()->first();

        // 3. Admin reconciles by linking existing product
        $service = app(InventoryReconciliationService::class);
        $service->linkExistingProduct(
            reconciliation: $recon,
            productId: $product->id,
            purchaseItemId: null,
            deductStock: true,
            notes: 'Physical stock verified',
            userId: $this->admin->id
        );

        $recon->refresh();
        $this->assertEquals('reconciled', $recon->status);
        $this->assertEquals($product->id, $recon->reconciled_product_id);
        $this->assertEquals($this->admin->id, $recon->reconciled_by);
        $this->assertNotNull($recon->reconciled_at);

        // Verify product stock was decremented from 10 to 8
        $product->refresh();
        $this->assertEquals(8, $product->quantity);

        // Verify sale item is now linked to product
        $saleItem = $recon->saleItem->fresh();
        $this->assertEquals($product->id, $saleItem->product_id);
    }

    public function test_admin_can_create_new_master_product_and_reconcile(): void
    {
        // 1. Create a manual sale
        $payload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 450.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Brand New Hair Serum',
                    'barcode' => '998877665544',
                    'quantity' => 1,
                    'unit_price' => 450.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $this->actingAs($this->salesUser)->postJson(route('sales.store'), $payload);
        $recon = InventoryReconciliation::latest()->first();

        // 2. Admin creates product in master catalog & reconciles
        $service = app(InventoryReconciliationService::class);
        $service->createNewProductAndReconcile(
            reconciliation: $recon,
            productData: [
                'name' => 'Brand New Hair Serum 50ml',
                'sku' => 'SKU-SERUM-50',
                'product_code' => '998877665544',
                'company_id' => $this->company->id,
                'category_id' => $this->category->id,
                'unit_id' => $this->unit->id,
                'selling_price' => 450.00,
                'purchase_price' => 250.00,
            ],
            initialStock: 10,
            deductSoldStock: true,
            notes: 'Registered new stock from vendor',
            userId: $this->admin->id
        );

        $recon->refresh();
        $this->assertEquals('reconciled', $recon->status);
        $this->assertNotNull($recon->reconciled_product_id);

        $newProduct = Product::where('sku', 'SKU-SERUM-50')->first();
        $this->assertNotNull($newProduct);
        $this->assertEquals('Brand New Hair Serum 50ml', $newProduct->name);
        $this->assertEquals(9, $newProduct->quantity); // 10 initial - 1 sold = 9 remaining

        $saleItem = $recon->saleItem->fresh();
        $this->assertEquals($newProduct->id, $saleItem->product_id);
    }

    public function test_admin_can_direct_mark_as_reconciled(): void
    {
        $payload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 200.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Promotional Hair Clip',
                    'quantity' => 1,
                    'unit_price' => 200.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $this->actingAs($this->salesUser)->postJson(route('sales.store'), $payload);
        $recon = InventoryReconciliation::latest()->first();

        $service = app(InventoryReconciliationService::class);
        $service->markAsReconciledDirectly(
            reconciliation: $recon,
            notes: 'Stock audited in weekly review',
            userId: $this->admin->id
        );

        $recon->refresh();
        $this->assertEquals('reconciled', $recon->status);
        $this->assertStringContainsString('Stock audited in weekly review', $recon->notes);
    }

    public function test_admin_can_edit_all_fields_of_reconciliation_record(): void
    {
        $payload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 300.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Mistyped Name',
                    'barcode' => '111',
                    'quantity' => 1,
                    'unit_price' => 300.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $this->actingAs($this->salesUser)->postJson(route('sales.store'), $payload);
        $recon = InventoryReconciliation::latest()->first();

        $service = app(InventoryReconciliationService::class);
        $service->updateReconciliationDetails(
            reconciliation: $recon,
            data: [
                'item_name' => 'Corrected Product Name',
                'barcode' => '222333',
                'quantity' => 2,
                'unit_price' => 250.00,
                'discount' => 20.00,
                'cost_price' => 150.00,
                'status' => 'pending',
                'notes' => 'Updated by Admin correction',
            ],
            syncWithSaleItem: true
        );

        $recon->refresh();
        $this->assertEquals('Corrected Product Name', $recon->item_name);
        $this->assertEquals('222333', $recon->barcode);
        $this->assertEquals(2, $recon->quantity);
        $this->assertEquals(250.00, (float) $recon->unit_price);
        $this->assertEquals(20.00, (float) $recon->discount);
        $this->assertEquals(460.00, (float) $recon->subtotal); // (250 - 20) * 2 = 460

        // Check synchronized sale item
        $saleItem = $recon->saleItem->fresh();
        $this->assertEquals('Corrected Product Name', $saleItem->custom_name);
        $this->assertEquals(460.00, (float) $saleItem->subtotal);

        // Check recalculated sale total
        $sale = $recon->sale->fresh();
        $this->assertEquals(460.00, (float) $sale->total);
    }

    public function test_auto_reconcile_matches_product_by_barcode(): void
    {
        $product = Product::create([
            'name' => 'Kerastase Elixir 50ml',
            'sku' => 'SKU-KER-50',
            'product_code' => '778899001122',
            'selling_price' => 1200.00,
            'purchase_price' => 700.00,
            'quantity' => 5,
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'location' => PurchaseLocation::SHOP,
        ]);
        InventoryStock::create([
            'product_id' => $product->id,
            'location' => PurchaseLocation::SHOP,
            'quantity' => 5,
        ]);

        $payload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 1200.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Elixir 50ml (Manual)',
                    'barcode' => '778899001122',
                    'quantity' => 1,
                    'unit_price' => 1200.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $this->actingAs($this->salesUser)->postJson(route('sales.store'), $payload);
        $recon = InventoryReconciliation::latest()->first();

        $service = app(InventoryReconciliationService::class);
        $reconciled = $service->autoReconcile($recon, $this->admin->id);

        $this->assertTrue($reconciled);
        $recon->refresh();
        $this->assertEquals('reconciled', $recon->status);
        $this->assertEquals($product->id, $recon->reconciled_product_id);
    }

    public function test_livewire_reconciliation_table_renders_and_filters(): void
    {
        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-001',
            'customer_id' => null,
            'created_by' => $this->salesUser->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 100,
            'total' => 100,
        ]);

        $saleItem = $sale->items()->create([
            'custom_name' => 'Sample Test Product',
            'is_manual' => true,
            'quantity' => 1,
            'cost_price' => 50,
            'unit_price' => 100,
            'final_price' => 100,
            'subtotal' => 100,
        ]);

        InventoryReconciliation::create([
            'reconciliation_number' => 'REC.261112.0001',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Sample Test Product',
            'quantity' => 1,
            'unit_price' => 100,
            'subtotal' => 100,
            'status' => 'pending',
            'created_by' => $this->salesUser->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(InventoryReconciliationTable::class)
            ->assertSee('Sample Test Product')
            ->assertSee('REC.261112.0001')
            ->assertSee('Pending Action');
    }

    public function test_admin_can_update_historical_sale_with_manual_item(): void
    {
        // 1. Create a sale with a manual item
        $createPayload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 200.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'is_manual' => true,
                    'name' => 'Original Hair Wax',
                    'barcode' => '89000111222',
                    'quantity' => 1,
                    'unit_price' => 200.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $this->actingAs($this->salesUser)->postJson(route('sales.store'), $createPayload);
        $sale = Sale::latest()->first();
        $this->assertNotNull($sale);
        $saleItem = $sale->items()->first();
        $this->assertNotNull($saleItem);

        $initialRecon = InventoryReconciliation::where('sale_item_id', $saleItem->id)->first();
        $this->assertNotNull($initialRecon);
        $reconNumber = $initialRecon->reconciliation_number;

        // 2. Admin edits historical sale: change quantity from 1 to 3, update name
        $updatePayload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 600.00,
            'status' => SaleStatus::COMPLETED->value,
            'reason' => 'Customer requested 2 more units of wax',
            'items' => [
                [
                    'id' => $saleItem->id,
                    'is_manual' => true,
                    'name' => 'Original Hair Wax Strong Hold',
                    'barcode' => '89000111222',
                    'quantity' => 3,
                    'unit_price' => 200.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('sales.update', $sale), $updatePayload);

        $response->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $this->assertEquals(600.00, (float) $sale->total);

        // Verify reconciliation record kept same number and updated fields
        $updatedRecon = InventoryReconciliation::where('sale_id', $sale->id)->first();
        $this->assertNotNull($updatedRecon);
        $this->assertEquals($reconNumber, $updatedRecon->reconciliation_number);
        $this->assertEquals('Original Hair Wax Strong Hold', $updatedRecon->item_name);
        $this->assertEquals(3, $updatedRecon->quantity);
        $this->assertEquals(600.00, (float) $updatedRecon->subtotal);
        $this->assertEquals('pending', $updatedRecon->status);
        $this->assertStringContainsString('[Sale Edit]', $updatedRecon->notes);
    }

    public function test_admin_can_add_new_manual_item_during_historical_sale_edit(): void
    {
        // 1. Create a sale with a standard product
        $product = Product::create([
            'name' => 'Existing Master Shampoo',
            'product_code' => 'P-SHMP-01',
            'sku' => 'SKU-SHMP-01',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'company_id' => $this->company->id,
            'purchase_price' => 100.00,
            'selling_price' => 200.00,
            'quantity' => 10,
            'location' => PurchaseLocation::SHOP,
        ]);
        InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 100.00,
            'total_cost' => 1000.00,
        ]);

        $createPayload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 200.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 200.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $this->actingAs($this->salesUser)->postJson(route('sales.store'), $createPayload);
        $sale = Sale::latest()->first();

        // 2. Admin edits sale and adds a manual item alongside existing product
        $updatePayload = [
            'sale_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentMethod::CASH->value,
            'cash_received' => 450.00,
            'status' => SaleStatus::COMPLETED->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 200.00,
                    'discount' => 0.00,
                ],
                [
                    'is_manual' => true,
                    'name' => 'Imported Comb Manual',
                    'barcode' => '890777888',
                    'quantity' => 1,
                    'unit_price' => 250.00,
                    'discount' => 0.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('sales.update', $sale), $updatePayload);

        $response->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $this->assertEquals(450.00, (float) $sale->total);
        $this->assertCount(2, $sale->items);

        // Verify a new reconciliation record was created for the new manual item
        $recon = InventoryReconciliation::where('sale_id', $sale->id)->first();
        $this->assertNotNull($recon);
        $this->assertEquals('Imported Comb Manual', $recon->item_name);
        $this->assertEquals('pending', $recon->status);
        $this->assertEquals(250.00, (float) $recon->subtotal);
    }

    public function test_edit_historical_sale_view_renders_manual_items_and_controls(): void
    {
        $sale = Sale::create([
            'invoice_number' => 'INV-HIST-001',
            'customer_id' => null,
            'created_by' => $this->admin->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 150,
            'total' => 150,
        ]);

        $sale->items()->create([
            'custom_name' => 'Custom Manual Serum',
            'barcode' => '890999000',
            'is_manual' => true,
            'quantity' => 1,
            'cost_price' => 80,
            'unit_price' => 150,
            'final_price' => 150,
            'subtotal' => 150,
        ]);

        $response = $this->actingAs($this->admin)->get(route('sales.edit', $sale));

        $response->assertStatus(200);
        $response->assertSee('Add Manual Item');
        $response->assertSee('fetch-master-modal-edit');
        $response->assertSee('Custom Manual Serum');
    }

    public function test_admin_can_create_master_product_from_modal_and_draft_po(): void
    {
        $sale = Sale::create([
            'invoice_number' => 'INV-NEW-PROD-01',
            'created_by' => $this->salesUser->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 600,
            'total' => 600,
        ]);

        $saleItem = $sale->items()->create([
            'custom_name' => 'Brand New Hair Wax 100g',
            'barcode' => '890111222333',
            'is_manual' => true,
            'quantity' => 2,
            'cost_price' => 0,
            'unit_price' => 300,
            'final_price' => 300,
            'subtotal' => 600,
        ]);

        $recon = InventoryReconciliation::create([
            'reconciliation_number' => 'REC-TEST-PO-01',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'item_name' => 'Brand New Hair Wax 100g',
            'barcode' => '890111222333',
            'quantity' => 2,
            'unit_price' => 300,
            'discount' => 0,
            'subtotal' => 600,
            'status' => 'pending',
            'created_by' => $this->salesUser->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(InventoryReconciliationModal::class)
            ->dispatch('open-reconciliation-modal', id: $recon->id, tab: 'create')
            ->set('create_company_id', $this->company->id)
            ->set('create_category_id', $this->category->id)
            ->set('create_unit_id', $this->unit->id)
            ->set('create_selling_price', 300.00)
            ->set('create_mrp', 300.00)
            ->set('create_purchase_price', 180.00)
            ->call('submitCreateProductAndDraftPO')
            ->assertRedirect(route('purchases.create', ['recon_ids' => $recon->id]));

        // Master product must now exist
        $product = Product::where('product_code', '890111222333')->first();
        $this->assertNotNull($product);
        $this->assertEquals('Brand New Hair Wax 100g', $product->name);
        $this->assertEquals(0, $product->quantity);

        // Reconciliation should be linked to the new product, but still pending for PO arrival
        $recon->refresh();
        $this->assertEquals($product->id, $recon->matched_product_id);
        $this->assertEquals('pending', $recon->status);
    }

    public function test_receiving_po_automatically_settles_linked_pending_reconciliation(): void
    {
        $supplier = Supplier::create([
            'name' => 'Direct Distributor',
            'contact_person' => 'Distributor Person',
            'email' => 'dist@test.com',
            'phone' => '9876543210',
        ]);
        $supplier->companies()->attach($this->company->id);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Special Salon Keratin',
            'sku' => 'SKU-KERATIN-01',
            'product_code' => '890555444333',
            'quantity' => 0,
            'mrp' => 500,
            'purchase_price' => 250,
        ]);

        // 1. Walk-in customer buys 3 units before PO is entered
        $sale = Sale::create([
            'invoice_number' => 'INV-PRE-PO-01',
            'created_by' => $this->salesUser->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 1500,
            'total' => 1500,
        ]);

        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'barcode' => '890555444333',
            'is_manual' => false,
            'quantity' => 3,
            'cost_price' => 0,
            'unit_price' => 500,
            'final_price' => 500,
            'subtotal' => 1500,
        ]);

        $recon = InventoryReconciliation::create([
            'reconciliation_number' => 'REC-KERATIN-01',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'matched_product_id' => $product->id,
            'item_name' => 'Special Salon Keratin',
            'barcode' => '890555444333',
            'quantity' => 3,
            'unit_price' => 500,
            'discount' => 0,
            'subtotal' => 1500,
            'status' => 'pending',
            'created_by' => $this->salesUser->id,
        ]);

        // 2. Admin creates PO for 10 units with target recon_ids
        $storePayload = [
            'supplier_id' => $supplier->id,
            'company_id' => $this->company->id,
            'location' => PurchaseLocation::SHOP->value,
            'purchase_date' => now()->format('Y-m-d'),
            'recon_ids' => (string) $recon->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 200.00,
                    'mrp' => 500.00,
                    'discount_percent' => 60.0,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('purchases.store'), $storePayload);
        $purchase = Purchase::latest()->first();
        $this->assertNotNull($purchase);
        $response->assertRedirect(route('purchases.show', $purchase));

        // Assert target_purchase_id was linked to recon
        $recon->refresh();
        $this->assertEquals($purchase->id, $recon->target_purchase_id);
        $this->assertEquals('pending', $recon->status);

        // 3. Mark PO as received via PurchaseService
        $service = app(PurchaseService::class);
        $service->markAsReceived($purchase);

        // 4. Assert Reconciliation is now fully reconciled
        $recon->refresh();
        $this->assertEquals('reconciled', $recon->status);
        $this->assertEquals($purchase->id, $recon->reconciled_purchase_id);
        $this->assertNotNull($recon->reconciled_at);

        // 5. Assert Inventory: 10 inwarded - 3 sold = 7 in stock
        $product->refresh();
        $this->assertEquals(7, $product->quantity);

        // 6. Assert SaleItem cost_price updated to PO unit price (200.00)
        $saleItem->refresh();
        $this->assertEquals(200.00, (float) $saleItem->cost_price);
        $this->assertEquals($product->id, $saleItem->product_id);
    }

    public function test_bulk_po_generation_from_reconciliation_table(): void
    {
        $sale = Sale::create([
            'invoice_number' => 'INV-BULK-01',
            'created_by' => $this->salesUser->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 400,
            'total' => 400,
        ]);

        $item1 = $sale->items()->create([
            'custom_name' => 'Hair Spray 200ml',
            'is_manual' => true,
            'quantity' => 2,
            'cost_price' => 0,
            'unit_price' => 150,
            'final_price' => 150,
            'subtotal' => 300,
        ]);

        $item2 = $sale->items()->create([
            'custom_name' => 'Hair Gel 150ml',
            'is_manual' => true,
            'quantity' => 1,
            'cost_price' => 0,
            'unit_price' => 100,
            'final_price' => 100,
            'subtotal' => 100,
        ]);

        $product1 = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Hair Spray 200ml',
            'sku' => 'SKU-SPRAY-01',
        ]);

        $product2 = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Hair Gel 150ml',
            'sku' => 'SKU-GEL-01',
        ]);

        $recon1 = InventoryReconciliation::create([
            'reconciliation_number' => 'REC-BULK-01',
            'sale_id' => $sale->id,
            'sale_item_id' => $item1->id,
            'item_name' => 'Hair Spray 200ml',
            'matched_product_id' => $product1->id,
            'quantity' => 2,
            'unit_price' => 150,
            'subtotal' => 300,
            'status' => 'pending',
            'created_by' => $this->salesUser->id,
        ]);

        $recon2 = InventoryReconciliation::create([
            'reconciliation_number' => 'REC-BULK-02',
            'sale_id' => $sale->id,
            'sale_item_id' => $item2->id,
            'item_name' => 'Hair Gel 150ml',
            'matched_product_id' => $product2->id,
            'quantity' => 1,
            'unit_price' => 100,
            'subtotal' => 100,
            'status' => 'pending',
            'created_by' => $this->salesUser->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(InventoryReconciliationTable::class)
            ->set('selectedIds', [$recon1->id, $recon2->id])
            ->call('createPurchaseOrderForSelected')
            ->assertRedirect(route('purchases.create', ['recon_ids' => "{$recon1->id},{$recon2->id}"]));
    }

    public function test_admin_can_link_pending_reconciliation_to_existing_received_po_in_modal(): void
    {
        $supplier = Supplier::create([
            'name' => 'Vendor Past',
            'contact_person' => 'VP',
            'email' => 'vp@test.com',
            'phone' => '1112223334',
        ]);
        $supplier->companies()->attach($this->company->id);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Conditioner 500ml',
            'sku' => 'SKU-COND-500',
            'product_code' => '890777111222',
            'quantity' => 0,
        ]);

        // Inward 10 items through PO received
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'company_id' => $this->company->id,
            'invoice_number' => 'PO-PAST-001',
            'po_reference' => 'PO-PAST-001',
            'status' => PurchaseStatus::ORDERED->value,
            'location' => PurchaseLocation::SHOP->value,
            'purchase_date' => now(),
            'created_by' => $this->admin->id,
        ]);

        $purchaseItem = $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 180.00,
            'selling_price' => 350.00,
            'mrp' => 350.00,
            'discount_percent' => 0,
            'subtotal' => 1800.00,
        ]);

        app(PurchaseService::class)->markAsReceived($purchase);
        $product->refresh();
        $this->assertEquals(10, $product->quantity);

        // Cashier sold 2 units manually
        $sale = Sale::create([
            'invoice_number' => 'INV-MANUAL-COND',
            'created_by' => $this->salesUser->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 700,
            'total' => 700,
        ]);

        $saleItem = $sale->items()->create([
            'custom_name' => 'Conditioner 500ml',
            'barcode' => '890777111222',
            'is_manual' => true,
            'quantity' => 2,
            'cost_price' => 0,
            'unit_price' => 350,
            'final_price' => 350,
            'subtotal' => 700,
        ]);

        $recon = InventoryReconciliation::create([
            'reconciliation_number' => 'REC-COND-01',
            'sale_id' => $sale->id,
            'sale_item_id' => $saleItem->id,
            'matched_product_id' => $product->id,
            'item_name' => 'Conditioner 500ml',
            'barcode' => '890777111222',
            'quantity' => 2,
            'unit_price' => 350,
            'discount' => 0,
            'subtotal' => 700,
            'status' => 'pending',
            'created_by' => $this->salesUser->id,
        ]);

        // Admin links to this already received PO via modal
        Livewire::actingAs($this->admin)
            ->test(InventoryReconciliationModal::class)
            ->dispatch('open-reconciliation-modal', id: $recon->id, tab: 'po')
            ->call('selectPurchase', $purchase->id)
            ->set('selectedPurchaseItemId', $purchaseItem->id)
            ->call('submitLinkPurchase');

        $recon->refresh();
        $this->assertEquals('reconciled', $recon->status);
        $this->assertEquals($purchase->id, $recon->reconciled_purchase_id);
        $this->assertEquals($purchaseItem->id, $recon->reconciled_purchase_item_id);

        // Stock must have been deducted from 10 to 8
        $product->refresh();
        $this->assertEquals(8, $product->quantity);

        // Sale item cost price updated to 180.00
        $saleItem->refresh();
        $this->assertEquals(180.00, (float) $saleItem->cost_price);
    }
}
