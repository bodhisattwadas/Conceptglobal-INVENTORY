<?php

namespace Tests\Feature;

use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\PurchaseService;
use App\Services\VendorInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceivedEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Supplier $supplier;

    protected Company $company;

    protected Category $category;

    protected Unit $unit;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->supplier = Supplier::create([
            'name' => 'Apex Supplies',
            'contact_person' => 'Supplier Contact',
            'email' => 'apex@test.com',
            'phone' => '9876543210',
        ]);

        $this->company = Company::create([
            'company_code' => 'APX-01',
            'company_name' => 'Apex Brands',
            'short_name' => 'Apex',
        ]);

        $this->supplier->companies()->attach($this->company->id);

        $this->category = Category::create([
            'name' => 'Cosmetics',
            'slug' => 'cosmetics',
        ]);

        $this->unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        $this->product = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Skin Glow Serum',
            'product_code' => 'SGS-001',
            'mrp' => 500,
            'purchase_price' => 300,
            'selling_price' => 500,
            'quantity' => 0,
        ]);
    }

    protected function createReceivedPurchase(int $quantity = 10, float $unitPrice = 300, float $mrp = 500): Purchase
    {
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'invoice_number' => 'PO-20261027-0001',
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'location' => PurchaseLocation::SHOP,
            'status' => PurchaseStatus::ORDERED,
            'total' => $quantity * $unitPrice,
            'created_by' => $this->user->id,
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'selling_price' => $mrp,
            'subtotal' => $quantity * $unitPrice,
        ]);

        // Receive the purchase order via PurchaseService
        app(PurchaseService::class)->markAsReceived($purchase);
        app(VendorInvoiceService::class)->createFromPurchase($purchase, 'INV-001', null);

        return $purchase->fresh(['items', 'vendorInvoice']);
    }

    public function test_received_purchase_edit_screen_renders_successfully(): void
    {
        $purchase = $this->createReceivedPurchase(10, 300);

        $response = $this->actingAs($this->user)->get(route('purchases.edit', $purchase));

        $response->assertOk();
        $response->assertSee('Purchase Order Already Received');
        $response->assertSee('Skin Glow Serum');
        $response->assertSee('SGS-001');
    }

    public function test_received_purchase_allows_increasing_quantity_and_updates_stock_and_ledger(): void
    {
        $purchase = $this->createReceivedPurchase(10, 300);

        $this->assertEquals(10, $this->product->fresh()->quantity);
        $this->assertEquals(10, InventoryStock::where('product_id', $this->product->id)->value('quantity'));

        $response = $this->actingAs($this->user)->put(route('purchases.update', $purchase), [
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'purchase_date' => now()->toDateString(),
            'location' => 'shop',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 15,
                    'discount_percent' => 40,
                    'unit_price' => 300,
                    'selling_price' => 500,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchases.show', $purchase));
        $response->assertSessionHas('success');

        $item = $purchase->fresh()->items->first();
        $this->assertEquals(15, $item->quantity);
        $this->assertEquals(15, $item->received_quantity);
        $this->assertEquals(4500.0, (float) $item->subtotal);

        // Product quantity & Inventory stock updated
        $this->assertEquals(15, $this->product->fresh()->quantity);
        $this->assertEquals(15, InventoryStock::where('product_id', $this->product->id)->value('quantity'));

        // Movement logged
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'purchase_id' => $purchase->id,
            'type' => 'purchase_adjustment',
            'quantity' => 5,
            'balance_after' => 15,
        ]);

        // Purchase total & VendorInvoice updated
        $this->assertEquals(4500.0, (float) $purchase->fresh()->total);
        $this->assertEquals(4500.0, (float) $purchase->fresh()->vendorInvoice->amount);
    }

    public function test_received_purchase_allows_decreasing_quantity_when_units_are_not_sold(): void
    {
        $purchase = $this->createReceivedPurchase(10, 300);

        $response = $this->actingAs($this->user)->put(route('purchases.update', $purchase), [
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'purchase_date' => now()->toDateString(),
            'location' => 'shop',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 7,
                    'discount_percent' => 40,
                    'unit_price' => 300,
                    'selling_price' => 500,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchases.show', $purchase));
        $response->assertSessionHas('success');

        $item = $purchase->fresh()->items->first();
        $this->assertEquals(7, $item->quantity);
        $this->assertEquals(7, $item->received_quantity);
        $this->assertEquals(2100.0, (float) $item->subtotal);

        $this->assertEquals(7, $this->product->fresh()->quantity);
        $this->assertEquals(7, InventoryStock::where('product_id', $this->product->id)->value('quantity'));

        // Negative adjustment movement logged
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'purchase_id' => $purchase->id,
            'type' => 'purchase_adjustment',
            'quantity' => -3,
            'balance_after' => 7,
        ]);

        $this->assertEquals(2100.0, (float) $purchase->fresh()->total);
        $this->assertEquals(2100.0, (float) $purchase->fresh()->vendorInvoice->amount);
    }

    public function test_received_purchase_rejects_reducing_quantity_below_already_sold_units(): void
    {
        $purchase = $this->createReceivedPurchase(10, 300);

        // Simulate 4 units sold by setting received_quantity = 6 on the batch item
        $purchaseItem = $purchase->items->first();
        $purchaseItem->update(['received_quantity' => 6]);

        // Attempting to reduce quantity to 3 (when 4 are already sold) must be rejected
        $response = $this->actingAs($this->user)->put(route('purchases.update', $purchase), [
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'purchase_date' => now()->toDateString(),
            'location' => 'shop',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'discount_percent' => 40,
                    'unit_price' => 300,
                    'selling_price' => 500,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.quantity']);

        // Database remains unchanged
        $this->assertEquals(10, $purchaseItem->fresh()->quantity);
        $this->assertEquals(6, $purchaseItem->fresh()->received_quantity);
    }

    public function test_received_purchase_rejects_adding_or_removing_products(): void
    {
        $purchase = $this->createReceivedPurchase(10, 300);

        $product2 = Product::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Eye Gel Cream',
            'product_code' => 'EGC-002',
            'mrp' => 400,
            'purchase_price' => 250,
            'selling_price' => 400,
        ]);

        // Trying to add a new product to received PO
        $response = $this->actingAs($this->user)->put(route('purchases.update', $purchase), [
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'purchase_date' => now()->toDateString(),
            'location' => 'shop',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'discount_percent' => 40,
                    'unit_price' => 300,
                    'selling_price' => 500,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 5,
                    'discount_percent' => 20,
                    'unit_price' => 320,
                    'selling_price' => 400,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items']);
        $this->assertEquals(1, $purchase->fresh()->items()->count());
    }

    public function test_received_purchase_allows_editing_price_and_discount(): void
    {
        $purchase = $this->createReceivedPurchase(10, 300);

        // Edit discount from 40% to 50% (unit price becomes 250 from MRP 500)
        $response = $this->actingAs($this->user)->put(route('purchases.update', $purchase), [
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'purchase_date' => now()->toDateString(),
            'location' => 'shop',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'discount_percent' => 50,
                    'unit_price' => 250,
                    'selling_price' => 500,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchases.show', $purchase));
        $response->assertSessionHas('success');

        $item = $purchase->fresh()->items->first();
        $this->assertEquals(250.0, (float) $item->unit_price);
        $this->assertEquals(2500.0, (float) $item->subtotal);

        // Purchase total & invoice updated
        $this->assertEquals(2500.0, (float) $purchase->fresh()->total);
        $this->assertEquals(2500.0, (float) $purchase->fresh()->vendorInvoice->amount);

        // Product purchase price updated
        $this->assertEquals(250.0, (float) $this->product->fresh()->purchase_price);
    }

    public function test_received_purchase_rejects_changing_supplier(): void
    {
        $purchase = $this->createReceivedPurchase(10, 300);

        $supplier2 = Supplier::create([
            'name' => 'Beta Vendor',
            'contact_person' => 'Beta Person',
            'email' => 'beta@test.com',
            'phone' => '1122334455',
        ]);
        $supplier2->companies()->attach($this->company->id);

        $response = $this->actingAs($this->user)->put(route('purchases.update', $purchase), [
            'supplier_id' => $supplier2->id,
            'company_id' => $this->company->id,
            'purchase_date' => now()->toDateString(),
            'location' => 'shop',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'discount_percent' => 40,
                    'unit_price' => 300,
                    'selling_price' => 500,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['supplier_id']);
        $this->assertEquals($this->supplier->id, $purchase->fresh()->supplier_id);
    }

    public function test_received_purchase_shows_edit_button_on_show_page(): void
    {
        $purchase = $this->createReceivedPurchase(10, 300);

        $response = $this->actingAs($this->user)->get(route('purchases.show', $purchase));

        $response->assertOk();
        $response->assertSee(route('purchases.edit', $purchase));
    }

    public function test_received_purchase_allows_editing_mrp_and_updates_product_mrp_and_price_history(): void
    {
        $purchase = $this->createReceivedPurchase(10, 300, 500);

        // Edit MRP from 500 to 600 with 40% discount (agreed unit price becomes 360)
        $response = $this->actingAs($this->user)->put(route('purchases.update', $purchase), [
            'supplier_id' => $this->supplier->id,
            'company_id' => $this->company->id,
            'purchase_date' => now()->toDateString(),
            'location' => 'shop',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'mrp' => 600,
                    'discount_percent' => 40,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchases.show', $purchase));
        $response->assertSessionHas('success');

        $item = $purchase->fresh()->items->first();
        $this->assertEquals(360.0, (float) $item->unit_price);
        $this->assertEquals(600.0, (float) $item->selling_price);
        $this->assertEquals(3600.0, (float) $item->subtotal);

        // Purchase total & invoice updated
        $this->assertEquals(3600.0, (float) $purchase->fresh()->total);
        $this->assertEquals(3600.0, (float) $purchase->fresh()->vendorInvoice->amount);

        // Product MRP and purchase price updated
        $this->assertEquals(600.0, (float) $this->product->fresh()->mrp);
        $this->assertEquals(360.0, (float) $this->product->fresh()->purchase_price);

        // Price history logged
        $this->assertDatabaseHas('product_price_histories', [
            'product_id' => $this->product->id,
            'old_mrp' => 500,
            'new_mrp' => 600,
            'source' => 'purchase_edit',
        ]);
    }
}
