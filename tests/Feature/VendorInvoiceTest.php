<?php

namespace Tests\Feature;

use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Enums\VendorInvoiceStatus;
use App\Livewire\VendorInvoices\VendorInvoiceTable;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VendorInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private User $salesUser;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->salesUser = User::factory()->create(['role' => 'sales']);

        $this->supplier = Supplier::create([
            'name' => 'Apex Chem Supplies',
            'contact_person' => 'Bob Builder',
            'email' => 'bob@apex.com',
            'phone' => '9876543210',
        ]);
    }

    private function createInvoice(array $attributes = []): VendorInvoice
    {
        static $counter = 1;

        $purchase = Purchase::create([
            'invoice_number' => 'PO-TEST-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-11-16',
            'location' => PurchaseLocation::SHOP,
            'total' => 1500.00,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $this->admin->id,
        ]);

        $invoice = VendorInvoice::create(array_merge([
            'purchase_id' => $purchase->id,
            'supplier_id' => $this->supplier->id,
            'po_reference' => $purchase->invoice_number,
            'invoice_number' => null,
            'invoice_date' => null,
            'amount' => 1500.00,
            'vendor_additional_discount' => 0.00,
            'paid_amount' => 0.00,
            'status' => VendorInvoiceStatus::UNPAID,
        ], $attributes));

        $counter++;

        return $invoice;
    }

    public function test_guest_cannot_update_vendor_invoice(): void
    {
        $invoice = $this->createInvoice(['invoice_number' => 'ORIG-001']);

        $response = $this->put(route('vendor-invoices.update', $invoice), [
            'invoice_number' => 'GUEST-ATTEMPT',
        ]);

        $response->assertRedirect('/login');
        $this->assertSame('ORIG-001', $invoice->fresh()->invoice_number);
    }

    public function test_unauthorized_roles_cannot_update_vendor_invoice(): void
    {
        $invoice = $this->createInvoice(['invoice_number' => 'ORIG-001']);

        $response = $this->actingAs($this->salesUser)->put(route('vendor-invoices.update', $invoice), [
            'invoice_number' => 'SALES-ATTEMPT',
        ]);

        $response->assertForbidden();
        $this->assertSame('ORIG-001', $invoice->fresh()->invoice_number);
    }

    public function test_admin_can_update_vendor_invoice_details(): void
    {
        $invoice = $this->createInvoice(['invoice_number' => 'ORIG-001']);

        $response = $this->actingAs($this->admin)->put(route('vendor-invoices.update', $invoice), [
            'invoice_number' => 'ADMIN-UPDATED-001',
            'invoice_date' => '2026-11-10',
            'vendor_additional_discount' => 100.00,
        ]);

        $response->assertRedirect(route('vendor-invoices.show', $invoice));
        $response->assertSessionHas('success');

        $refreshed = $invoice->fresh();
        $this->assertSame('ADMIN-UPDATED-001', $refreshed->invoice_number);
        $this->assertSame('2026-11-10', $refreshed->invoice_date->format('Y-m-d'));
        $this->assertEquals(100.00, (float) $refreshed->vendor_additional_discount);
    }

    public function test_manager_can_update_vendor_invoice_details(): void
    {
        $invoice = $this->createInvoice(['invoice_number' => 'ORIG-001']);

        $response = $this->actingAs($this->manager)->patch(route('vendor-invoices.update', $invoice), [
            'invoice_number' => 'MGR-UPDATED-002',
            'invoice_date' => '2026-11-12',
            'additional_discount' => 50.00,
        ]);

        $response->assertRedirect(route('vendor-invoices.show', $invoice));
        $response->assertSessionHas('success');

        $refreshed = $invoice->fresh();
        $this->assertSame('MGR-UPDATED-002', $refreshed->invoice_number);
        $this->assertSame('2026-11-12', $refreshed->invoice_date->format('Y-m-d'));
        $this->assertEquals(50.00, (float) $refreshed->vendor_additional_discount);
    }

    public function test_vendor_invoice_document_upload_is_stored_on_public_disk(): void
    {
        Storage::fake('public');

        $invoice = $this->createInvoice(['invoice_number' => 'DOC-001']);
        $file = UploadedFile::fake()->create('supplier_invoice.pdf', 250, 'application/pdf');

        $response = $this->actingAs($this->admin)->put(route('vendor-invoices.update', $invoice), [
            'invoice_number' => 'DOC-001',
            'document' => $file,
        ]);

        $response->assertRedirect(route('vendor-invoices.show', $invoice));
        $refreshed = $invoice->fresh();
        $this->assertNotNull($refreshed->document_path);
        Storage::disk('public')->assertExists($refreshed->document_path);
    }

    public function test_vendor_invoice_show_page_renders_edit_details_modal_and_current_values(): void
    {
        $invoice = $this->createInvoice([
            'invoice_number' => 'SHOW-MODAL-999',
            'invoice_date' => '2026-11-14',
            'vendor_additional_discount' => 75.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('vendor-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('edit-invoice-details-modal');
        $response->assertSee('Edit Invoice Details');
        $response->assertSee('SHOW-MODAL-999');
        $response->assertSee('2026-11-14');
        $response->assertSee('75.00');
    }

    public function test_vendor_invoice_table_renders_warning_badge_for_missing_invoice_number(): void
    {
        $blankInvoice = $this->createInvoice(['invoice_number' => null]);
        $populatedInvoice = $this->createInvoice(['invoice_number' => 'POP-INV-1234']);

        Livewire::actingAs($this->admin)
            ->test(VendorInvoiceTable::class)
            ->assertSeeHtml('Missing')
            ->assertSeeHtml('border-amber-200 bg-amber-50')
            ->assertSee('POP-INV-1234');
    }

    public function test_artisan_command_handles_no_blank_invoices(): void
    {
        $this->artisan('vendor-invoices:update-missing')
            ->expectsOutputToContain('No blank vendor invoices found.')
            ->assertExitCode(0);
    }

    public function test_artisan_command_updates_blank_vendor_invoices_interactively(): void
    {
        $inv1 = $this->createInvoice(['invoice_number' => null]);
        $inv2 = $this->createInvoice(['invoice_number' => '']);

        $this->artisan('vendor-invoices:update-missing')
            ->expectsQuestion("Enter supplier invoice number for Invoice #{$inv1->id} (type 'skip' to skip, 'cancel' to exit)", 'SUP-INV-1001')
            ->expectsQuestion('Enter invoice date (YYYY-MM-DD)', '2026-11-15')
            ->expectsOutputToContain("Successfully updated Invoice #{$inv1->id}")
            ->expectsQuestion("Enter supplier invoice number for Invoice #{$inv2->id} (type 'skip' to skip, 'cancel' to exit)", 'SUP-INV-1002')
            ->expectsQuestion('Enter invoice date (YYYY-MM-DD)', '2026-11-16')
            ->expectsOutputToContain("Successfully updated Invoice #{$inv2->id}")
            ->expectsOutputToContain('Successfully updated:       2')
            ->assertExitCode(0);

        $this->assertEquals('SUP-INV-1001', $inv1->fresh()->invoice_number);
        $this->assertEquals('2026-11-15', $inv1->fresh()->invoice_date->format('Y-m-d'));

        $this->assertEquals('SUP-INV-1002', $inv2->fresh()->invoice_number);
        $this->assertEquals('2026-11-16', $inv2->fresh()->invoice_date->format('Y-m-d'));
    }

    public function test_artisan_command_skips_invoice_when_requested(): void
    {
        $inv1 = $this->createInvoice(['invoice_number' => null]);
        $inv2 = $this->createInvoice(['invoice_number' => null]);

        $this->artisan('vendor-invoices:update-missing')
            ->expectsQuestion("Enter supplier invoice number for Invoice #{$inv1->id} (type 'skip' to skip, 'cancel' to exit)", 'skip')
            ->expectsOutputToContain("Skipped Invoice #{$inv1->id}.")
            ->expectsQuestion("Enter supplier invoice number for Invoice #{$inv2->id} (type 'skip' to skip, 'cancel' to exit)", 'SUP-INV-2002')
            ->expectsQuestion('Enter invoice date (YYYY-MM-DD)', '2026-11-16')
            ->expectsOutputToContain("Successfully updated Invoice #{$inv2->id}")
            ->expectsOutputToContain('Skipped:                    1')
            ->expectsOutputToContain('Successfully updated:       1')
            ->assertExitCode(0);

        $this->assertNull($inv1->fresh()->invoice_number);
        $this->assertEquals('SUP-INV-2002', $inv2->fresh()->invoice_number);
    }

    public function test_artisan_command_cancels_operation_when_user_types_cancel(): void
    {
        $inv1 = $this->createInvoice(['invoice_number' => null]);
        $inv2 = $this->createInvoice(['invoice_number' => null]);

        $this->artisan('vendor-invoices:update-missing')
            ->expectsQuestion("Enter supplier invoice number for Invoice #{$inv1->id} (type 'skip' to skip, 'cancel' to exit)", 'cancel')
            ->expectsOutputToContain('Batch update cancelled by user.')
            ->expectsOutputToContain('Cancelled early. Remaining unprocessed: 2')
            ->assertExitCode(0);

        $this->assertNull($inv1->fresh()->invoice_number);
        $this->assertNull($inv2->fresh()->invoice_number);
    }

    public function test_artisan_command_validates_and_skips_on_invalid_date(): void
    {
        $inv = $this->createInvoice(['invoice_number' => null]);

        $this->artisan('vendor-invoices:update-missing')
            ->expectsQuestion("Enter supplier invoice number for Invoice #{$inv->id} (type 'skip' to skip, 'cancel' to exit)", 'SUP-INV-3001')
            ->expectsQuestion('Enter invoice date (YYYY-MM-DD)', 'invalid-date-format')
            ->expectsOutputToContain("Invalid date format 'invalid-date-format'. Expected YYYY-MM-DD.")
            ->expectsOutputToContain('Skipped:                    1')
            ->assertExitCode(0);

        $this->assertNull($inv->fresh()->invoice_number);
    }

    public function test_vendor_invoice_show_page_accepts_decimal_in_paid_amount_input(): void
    {
        $invoice = $this->createInvoice(['amount' => 1250.75, 'paid_amount' => 0.00]);

        $response = $this->actingAs($this->admin)->get(route('vendor-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('step="any"', false);
        $response->assertSee('min="0.01"', false);
        $response->assertSee('value="1250.75"', false);
    }

    public function test_admin_can_mark_vendor_invoice_paid_with_decimal_amount(): void
    {
        $invoice = $this->createInvoice(['amount' => 655.70, 'paid_amount' => 0.00]);

        $response = $this->actingAs($this->admin)->patch(route('vendor-invoices.mark-paid', $invoice), [
            'payment_method' => 'bank_transfer',
            'paid_amount' => 655.7,
            'paid_at' => '2026-11-16',
            'payment_reference' => 'UTR99887766',
            'payment_notes' => 'Settled with decimals',
        ]);

        $response->assertRedirect(route('vendor-invoices.show', $invoice));
        $response->assertSessionHas('success');

        $refreshed = $invoice->fresh();
        $this->assertEquals(655.70, (float) $refreshed->paid_amount);
        $this->assertEquals(VendorInvoiceStatus::PAID, $refreshed->status);
        $this->assertSame('bank_transfer', $refreshed->payment_method);
        $this->assertSame('UTR99887766', $refreshed->payment_reference);
    }

    public function test_admin_can_make_partial_payment_with_decimal_amount(): void
    {
        $invoice = $this->createInvoice(['amount' => 1000.50, 'paid_amount' => 0.00]);

        $response = $this->actingAs($this->admin)->patch(route('vendor-invoices.mark-paid', $invoice), [
            'payment_method' => 'upi',
            'paid_amount' => 300.25,
            'paid_at' => '2026-11-16',
            'payment_reference' => 'UPI12345',
        ]);

        $response->assertRedirect(route('vendor-invoices.show', $invoice));
        $refreshed = $invoice->fresh();
        $this->assertEquals(300.25, (float) $refreshed->paid_amount);
        $this->assertEquals(VendorInvoiceStatus::PARTIALLY_PAID, $refreshed->status);
    }
}
