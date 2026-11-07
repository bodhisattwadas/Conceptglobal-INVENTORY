<?php

namespace Tests\Feature;

use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Enums\VendorInvoiceStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Models\VendorReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorReturnTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $manager;

    protected User $salesUser;

    protected Company $brand;

    protected Category $category;

    protected Unit $unit;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->salesUser = User::factory()->create(['role' => UserRole::Sales]);

        $this->brand = Company::create(['company_name' => 'Mamaearth', 'short_name' => 'ME', 'company_code' => 'ME']);
        $this->category = Category::create(['name' => 'Hair Care', 'slug' => 'hair-care']);
        $this->unit = Unit::create(['name' => 'Pieces', 'symbol' => 'pcs']);
        $this->supplier = Supplier::factory()->create();
    }

    public function test_admin_and_manager_can_access_vendor_returns_pages(): void
    {
        $this->actingAs($this->admin)->get(route('vendor-returns.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('vendor-returns.create'))->assertOk();

        $this->actingAs($this->manager)->get(route('vendor-returns.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('vendor-returns.create'))->assertOk();

        $this->actingAs($this->salesUser)->get(route('vendor-returns.index'))->assertForbidden();
    }

    public function test_can_create_vendor_return_successfully(): void
    {
        $product = Product::create([
            'name' => 'Onion Shampoo 250ml',
            'sku' => 'SKU-OS-250',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 349,
            'purchase_price' => 200,
            'quantity' => 20,
        ]);

        InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-TEST-100',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'created_by' => $this->admin->id,
            'purchase_date' => now()->subDays(3),
            'status' => PurchaseStatus::RECEIVED,
            'location' => PurchaseLocation::SHOP,
            'total' => 4000,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'BATCH-ME-99',
            'quantity' => 20,
            'ordered_quantity' => 20,
            'received_quantity' => 20,
            'unit_price' => 200,
            'subtotal' => 4000,
            'location' => PurchaseLocation::SHOP,
        ]);

        $vendorInvoice = VendorInvoice::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'po_reference' => 'PO-TEST-100',
            'invoice_number' => 'INV-SUP-100',
            'invoice_date' => now()->subDays(3),
            'amount' => 4000,
            'paid_amount' => 0,
            'status' => VendorInvoiceStatus::UNPAID,
        ]);

        // Return 5 damaged units back to the vendor
        $response = $this->actingAs($this->admin)->post(route('vendor-returns.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => now()->format('Y-m-d'),
            'reason' => 'damaged',
            'notes' => '5 bottles broken during courier delivery',
            'items' => [
                [
                    'purchase_item_id' => $purchaseItem->id,
                    'quantity' => 5,
                    'reason' => 'damaged',
                ],
            ],
        ]);

        $vendorReturn = VendorReturn::latest('id')->first();
        $this->assertNotNull($vendorReturn);
        $response->assertRedirect(route('vendor-returns.show', $vendorReturn));

        // 1. Verify Vendor Return amounts
        $this->assertEquals(1000, (float) $vendorReturn->total_amount); // 5 * 200
        $this->assertEquals('damaged', $vendorReturn->reason);
        $this->assertStringStartsWith('VR-', $vendorReturn->return_number);

        // 2. Verify batch stock was decremented from 20 to 15
        $this->assertEquals(15, $purchaseItem->fresh()->received_quantity);

        // 3. Verify total product and inventory stock decremented from 20 to 15
        $this->assertEquals(15, $product->fresh()->quantity);
        $this->assertEquals(15, InventoryStock::where('product_id', $product->id)->value('quantity'));

        // 4. Verify InventoryMovement logged as vendor_return with -5 quantity
        $movement = InventoryMovement::where('product_id', $product->id)
            ->where('type', 'vendor_return')
            ->latest('id')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-5, $movement->quantity);
        $this->assertEquals(15, $movement->balance_after);
        $this->assertEquals($vendorReturn->return_number, $movement->reference);

        // 5. Verify Vendor Invoice amount reduced from 4000 to 3000
        $vendorInvoice->refresh();
        $this->assertEquals(3000, (float) $vendorInvoice->amount);
        $this->assertStringContainsString($vendorReturn->return_number, $vendorInvoice->payment_notes);
    }

    public function test_cannot_return_more_than_available_batch_stock(): void
    {
        $product = Product::create([
            'name' => 'Body Wash',
            'sku' => 'SKU-BW-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 200,
            'purchase_price' => 100,
            'quantity' => 5,
        ]);

        InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-TEST-101',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'created_by' => $this->admin->id,
            'purchase_date' => now()->subDays(2),
            'status' => PurchaseStatus::RECEIVED,
            'total' => 500,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'BATCH-01',
            'quantity' => 5,
            'ordered_quantity' => 5,
            'received_quantity' => 5, // only 5 available
            'unit_price' => 100,
            'subtotal' => 500,
        ]);

        // Attempt to return 10 units when only 5 exist
        $response = $this->actingAs($this->admin)->post(route('vendor-returns.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => now()->format('Y-m-d'),
            'reason' => 'expired',
            'items' => [
                [
                    'purchase_item_id' => $purchaseItem->id,
                    'quantity' => 10,
                ],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, VendorReturn::count());
        $this->assertEquals(5, $product->fresh()->quantity);
    }

    public function test_cannot_return_on_unreceived_purchase(): void
    {
        $product = Product::create([
            'name' => 'Hair Mask',
            'sku' => 'SKU-HM-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 500,
            'purchase_price' => 300,
            'quantity' => 0,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-TEST-ORDERED',
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'created_by' => $this->admin->id,
            'purchase_date' => now(),
            'status' => PurchaseStatus::ORDERED, // Not yet received!
            'total' => 300,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'ordered_quantity' => 1,
            'received_quantity' => 0,
            'unit_price' => 300,
            'subtotal' => 300,
        ]);

        $response = $this->actingAs($this->admin)->post(route('vendor-returns.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => now()->format('Y-m-d'),
            'reason' => 'wrong_item',
            'items' => [
                [
                    'purchase_item_id' => $purchaseItem->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, VendorReturn::count());
    }

    public function test_printable_debit_note_renders_correctly(): void
    {
        $product = Product::create([
            'name' => 'Face Serum',
            'sku' => 'SKU-FS-01',
            'company_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 600,
            'purchase_price' => 400,
            'quantity' => 10,
        ]);

        $purchase = Purchase::create([
            'invoice_number' => 'PO-PRINT-TEST',
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'purchase_date' => now(),
            'status' => PurchaseStatus::RECEIVED,
            'total' => 4000,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'BATCH-FS-PRINT',
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 400,
            'subtotal' => 4000,
        ]);

        $vendorReturn = VendorReturn::create([
            'return_number' => 'VR-2026-9999',
            'purchase_id' => $purchase->id,
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->brand->id,
            'return_date' => now(),
            'total_amount' => 800,
            'status' => 'completed',
            'reason' => 'defective',
            'notes' => 'Leaking bottles',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('vendor-returns.print', $vendorReturn));
        $response->assertOk();
        $response->assertSee('DEBIT NOTE');
        $response->assertSee('VR-2026-9999');
        $response->assertSee($this->supplier->name);
    }
}
