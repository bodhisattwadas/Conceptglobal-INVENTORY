<?php

namespace Tests\Feature;

use App\Livewire\Inventory\InventoryTable;
use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_page_can_be_rendered(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('inventory.index'));

        $response->assertOk();
        $response->assertSeeLivewire('inventory.inventory-table');
    }

    public function test_batch_quantities_badge_renders_flexible_pill_without_rigid_grid(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Supplier X',
            'contact_person' => 'Person X',
            'email' => 'supplier@test.com',
            'phone' => '1234567890',
        ]);

        $company = Company::create([
            'company_code' => 'CMP-01',
            'company_name' => 'Company One',
        ]);

        $category = Category::create([
            'name' => 'Skincare',
            'slug' => 'skincare',
        ]);

        $unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        $product = Product::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 450,
            'selling_price' => 600,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'created_by' => $user->id,
            'total' => 4500,
        ]);

        $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 450,
            'subtotal' => 4500,
            'batch_number' => 'LOT-2026-LONG-BATCH-CODE',
        ]);

        $stock = InventoryStock::create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $stock->load(['product.purchaseItems.purchase']);

        $table = new InventoryTable;
        $reflection = new ReflectionClass($table);
        $method = $reflection->getMethod('batchQuantities');
        $method->setAccessible(true);

        $html = $method->invoke($table, $stock);

        // Ensure rigid grid is replaced with flexible pill
        $this->assertStringNotContainsString('inline-grid', $html);
        $this->assertStringNotContainsString('w-64', $html);
        $this->assertStringNotContainsString('grid-cols-[8.75rem_2.25rem_5rem]', $html);

        // Ensure clean flexible pill markup
        $this->assertStringContainsString('inline-flex', $html);
        $this->assertStringContainsString('LOT-2026-LONG-BATCH-CODE', $html);
        $this->assertStringContainsString('truncate', $html);
        $this->assertStringContainsString('whitespace-nowrap', $html);
        $this->assertStringContainsString('10', $html);
        $this->assertStringContainsString(format_money(450), $html);
    }

    public function test_inventory_table_and_print_include_items_with_zero_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $category = Category::create([
            'name' => 'General',
            'slug' => 'general',
        ]);
        $unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        $productInStock = Product::factory()->create([
            'name' => 'Available Item',
            'sku' => 'SKU-IN-STOCK',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'quantity' => 15,
        ]);
        InventoryStock::create([
            'product_id' => $productInStock->id,
            'quantity' => 15,
        ]);

        $productZero = Product::factory()->create([
            'name' => 'Out Of Stock Item',
            'sku' => 'SKU-ZERO-COUNT',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'quantity' => 0,
        ]);
        InventoryStock::create([
            'product_id' => $productZero->id,
            'quantity' => 0,
        ]);

        // 1. Datasource query check
        $table = new InventoryTable;
        $stocks = $table->datasource()->get();

        $this->assertTrue($stocks->contains('sku', 'SKU-IN-STOCK'));
        $this->assertTrue($stocks->contains('sku', 'SKU-ZERO-COUNT'));

        // 2. Livewire component rendering check
        Livewire::actingAs($admin)
            ->test(InventoryTable::class)
            ->assertSee('SKU-IN-STOCK')
            ->assertSee('Available Item')
            ->assertSee('SKU-ZERO-COUNT')
            ->assertSee('Out Of Stock Item');

        // 3. Print report check
        $printResponse = $this->actingAs($admin)->get(route('inventory.print'));
        $printResponse->assertOk();
    }
}
