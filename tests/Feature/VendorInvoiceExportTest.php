<?php

namespace Tests\Feature;

use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Enums\VendorInvoiceStatus;
use App\Livewire\VendorInvoices\VendorInvoiceTable;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use Tests\TestCase;

class VendorInvoiceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_invoices_export_without_html_tags_and_scripts(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User', 'role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Alpha Cosmetics Ltd',
            'contact_person' => 'Jane Vendor',
            'email' => 'jane@alpha.com',
            'phone' => '9876543210',
        ]);

        $company = Company::create([
            'company_code' => 'BRD-ALPHA',
            'company_name' => 'Alpha Glow',
            'short_name' => 'AG',
        ]);

        $purchase1 = Purchase::create([
            'invoice_number' => 'PO-20261116-0001',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-11-16',
            'location' => PurchaseLocation::SHOP,
            'total' => 5000.00,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $admin->id,
        ]);

        $invoice1 = VendorInvoice::create([
            'purchase_id' => $purchase1->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'po_reference' => 'PO-20261116-0001',
            'invoice_number' => 'INV-SUP-9901',
            'amount' => 5000.00,
            'vendor_additional_discount' => 200.00,
            'paid_amount' => 5000.00,
            'status' => VendorInvoiceStatus::PAID,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TXN-BANK-001',
            'paid_at' => now(),
            'paid_by' => $admin->id,
        ]);

        $purchase2 = Purchase::create([
            'invoice_number' => 'PO-20261116-0002',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-11-16',
            'location' => PurchaseLocation::SHOP,
            'total' => 3000.00,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $admin->id,
        ]);

        $invoice2 = VendorInvoice::create([
            'purchase_id' => $purchase2->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'po_reference' => 'PO-20261116-0002',
            'invoice_number' => null, // Empty invoice number to verify no <span> tag exported
            'amount' => 3000.00,
            'vendor_additional_discount' => 0.00,
            'paid_amount' => 1000.00,
            'status' => VendorInvoiceStatus::PARTIALLY_PAID,
            'payment_method' => 'cash',
            'payment_reference' => null,
            'paid_at' => null,
            'paid_by' => null,
        ]);

        $component = new VendorInvoiceTable;
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
        $this->assertCount(2, $prepared['rows']);

        // Check first invoice row (Paid)
        $row1 = $prepared['rows'][0];
        $this->assertSame('PO-20261116-0001', $row1['PO Reference']);
        $this->assertSame('INV-SUP-9901', $row1['Invoice Number']);
        $this->assertSame('Alpha Cosmetics Ltd', $row1['Supplier / Vendor']);
        $this->assertSame('AG', $row1['Brand / Company']);
        $this->assertSame(format_money(5000.00), $row1['Amount']);
        $this->assertSame('+ '.format_money(200.00), $row1['Vendor Discount']);
        $this->assertSame(format_money(5000.00), $row1['Paid']);
        $this->assertSame(format_money(0.00), $row1['Due']);
        $this->assertSame('Fully Paid', $row1['Status']);
        $this->assertArrayNotHasKey('', $row1, 'Action column should not be exported');

        // Check second invoice row (Partially Paid with empty invoice number)
        $row2 = $prepared['rows'][1];
        $this->assertSame('PO-20261116-0002', $row2['PO Reference']);
        $this->assertSame('-', $row2['Invoice Number']);
        $this->assertSame('Alpha Cosmetics Ltd', $row2['Supplier / Vendor']);
        $this->assertSame('AG', $row2['Brand / Company']);
        $this->assertSame(format_money(3000.00), $row2['Amount']);
        $this->assertSame('-', $row2['Vendor Discount']);
        $this->assertSame(format_money(1000.00), $row2['Paid']);
        $this->assertSame(format_money(2000.00), $row2['Due']);
        $this->assertSame('Partially Paid', $row2['Status']);
        $this->assertSame('-', $row2['Paid At']);

        // Verify zero HTML tags across all exported cells
        foreach ($prepared['rows'] as $index => $row) {
            foreach ($row as $key => $value) {
                $this->assertStringNotContainsString(
                    '<',
                    (string) $value,
                    "Row {$index} Column '{$key}' contains HTML '<': {$value}"
                );
                $this->assertStringNotContainsString(
                    '>',
                    (string) $value,
                    "Row {$index} Column '{$key}' contains HTML '>': {$value}"
                );
            }
        }
    }
}
