<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Livewire\Purchases\PurchaseTable;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_products_are_rejected_when_creating_a_purchase_order(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Test Vendor',
            'contact_person' => 'Vendor Person',
            'email' => 'vendor@test.com',
            'phone' => '1234567890',
        ]);

        $company = Company::create([
            'company_code' => 'BRD-001',
            'company_name' => 'Test Brand',
            'short_name' => 'TB',
        ]);

        $supplier->companies()->attach($company->id);

        $category = Category::create([
            'name' => 'Makeup',
            'slug' => 'makeup',
        ]);

        $unit = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        $product = Product::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'mrp' => 500,
            'purchase_price' => 400,
        ]);

        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'company_id' => $company->id,
                    'quantity' => 2,
                    'discount_percent' => 17,
                    'unit_price' => 415,
                    'selling_price' => 500,
                ],
                [
                    'product_id' => $product->id,
                    'company_id' => $company->id,
                    'quantity' => 5,
                    'discount_percent' => 17,
                    'unit_price' => 415,
                    'selling_price' => 500,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.product_id', 'items.1.product_id']);
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_purchase_orders_export_without_html_tags(): void
    {
        $user = User::factory()->create(['name' => 'John Admin', 'role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Acme Supplies',
            'contact_person' => 'Vendor Person',
            'email' => 'vendor@test.com',
            'phone' => '1234567890',
        ]);

        $company = Company::create([
            'company_code' => 'BRD-001',
            'company_name' => 'Acme Brand',
            'short_name' => 'AB',
        ]);

        Purchase::create([
            'invoice_number' => 'PO-20261018-0001',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-10-18',
            'total' => 1500.00,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
        ]);

        $component = new PurchaseTable;
        $component->boot();
        $component->columns = $component->columns();
        $component->setUp();

        $currentHiddenStates = collect($component->columns)
            ->mapWithKeys(fn ($column) => [data_get($column, 'field') => data_get($column, 'hidden')]);

        $columnsWithHiddenState = array_map(function ($column) use ($currentHiddenStates) {
            $column->hidden = $currentHiddenStates[$column->field];

            return $column;
        }, $component->columns());

        $export = new Export;
        $prepared = $export->prepare($component->prepareToExport(), $columnsWithHiddenState, true);

        $this->assertNotEmpty($prepared['rows']);
        $row = $prepared['rows'][0];

        $this->assertSame('PO-20261018-0001', $row['PO Reference']);
        $this->assertSame('Acme Supplies', $row['Supplier / Vendor']);
        $this->assertSame('AB', $row['Brand / Company']);
        $this->assertSame('18/10/2026', $row['PO Date']);
        $this->assertSame('Received', $row['Status']);
        $this->assertSame('John Admin', $row['Created By']);

        foreach ($row as $key => $value) {
            $this->assertStringNotContainsString('<', $value, "Column {$key} contains HTML tags: {$value}");
            $this->assertStringNotContainsString('>', $value, "Column {$key} contains HTML tags: {$value}");
        }
    }
}
