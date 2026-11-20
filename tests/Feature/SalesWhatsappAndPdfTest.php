<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SalesWhatsappAndPdfTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleSale(?Customer $customer = null, ?User $creator = null): Sale
    {
        $creator ??= User::factory()->create(['role' => 'sales']);
        $category = Category::create(['name' => 'Skincare', 'slug' => 'skincare-'.uniqid()]);
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Hydra Glow Serum',
            'selling_price' => 650.00,
        ]);
        $service = Service::create([
            'name' => 'Express Facial',
            'price' => 350.00,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV.261117.'.random_int(1000, 9999),
            'customer_id' => $customer?->id,
            'created_by' => $creator->id,
            'sale_date' => '2026-11-17 14:15:00',
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 1000.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 1000.00,
            'cash_received' => 1000.00,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 400.00,
            'unit_price' => 650.00,
            'discount' => 0,
            'final_price' => 650.00,
            'subtotal' => 650.00,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'cost_price' => 0,
            'unit_price' => 350.00,
            'discount' => 0,
            'final_price' => 350.00,
            'subtotal' => 350.00,
        ]);

        return $sale;
    }

    public function test_authenticated_cashier_or_admin_can_download_pdf_and_disallowed_roles_are_forbidden(): void
    {
        $salesUser = User::factory()->create(['role' => 'sales']);
        $adminUser = User::factory()->create(['role' => 'admin']);
        $managerUser = User::factory()->create(['role' => 'manager']);
        $staffUser = User::factory()->create(['role' => 'staff']);

        Setting::set('currency_symbol', '₹');
        $customer = Customer::factory()->create(['name' => 'Meera Nair', 'phone' => '+91 98765 43210']);
        $sale = $this->createSampleSale($customer, $salesUser);

        // Unauthenticated
        $this->get(route('sales.pdf', $sale))->assertRedirect(route('login'));

        // Disallowed roles
        $this->actingAs($managerUser)->get(route('sales.pdf', $sale))->assertForbidden();
        $this->actingAs($staffUser)->get(route('sales.pdf', $sale))->assertForbidden();

        // Sales user
        $salesResponse = $this->actingAs($salesUser)->get(route('sales.pdf', $sale));
        $salesResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $salesResponse->headers->get('Content-Type'));
        $this->assertStringContainsString("Invoice-{$sale->invoice_number}.pdf", (string) $salesResponse->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $salesResponse->getContent());

        // Admin user
        $adminResponse = $this->actingAs($adminUser)->get(route('sales.pdf', $sale));
        $adminResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $adminResponse->headers->get('Content-Type'));
    }

    public function test_signed_public_pdf_route_serves_pdf_without_auth_and_rejects_invalid_requests(): void
    {
        $sale = $this->createSampleSale();

        // Valid temporary signed URL works unauthenticated
        $signedUrl = URL::temporarySignedRoute('sales.public-pdf', now()->addDays(7), ['sale' => $sale->id]);
        $response = $this->get($signedUrl);
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        // Unsigned URL is rejected
        $this->get(route('sales.public-pdf', $sale))->assertForbidden();

        // Tampered query parameter is rejected
        $this->get($signedUrl.'&tampered=1')->assertForbidden();

        // Expired signed URL is rejected
        $expiredUrl = URL::temporarySignedRoute('sales.public-pdf', now()->subMinute(), ['sale' => $sale->id]);
        $this->get($expiredUrl)->assertForbidden();
    }

    public function test_send_whatsapp_endpoint_dispatches_wati_request_and_validates_phone(): void
    {
        Config::set('whatsapp.provider', 'wati');
        Config::set('wati.endpoint', 'https://live-server.wati.io/999');
        Config::set('wati.token', 'secret-wati-token');
        Config::set('wati.template_name', 'invoice_template');
        Config::set('wati.default_country_code', '91');

        $salesUser = User::factory()->create(['role' => 'sales']);
        $managerUser = User::factory()->create(['role' => 'manager']);
        $customer = Customer::factory()->create(['name' => 'Riya Sen', 'phone' => '9876543210']);
        $sale = $this->createSampleSale($customer, $salesUser);

        Http::fake([
            'https://live-server.wati.io/999/api/v1/sendTemplateMessage*' => Http::response([
                'result' => true,
                'info' => 'Queued',
            ], 200),
        ]);

        // Unauthenticated JSON request
        $this->postJson(route('sales.send-whatsapp', $sale), ['phone' => '9876543210'])
            ->assertUnauthorized();

        // Disallowed role
        $this->actingAs($managerUser)
            ->postJson(route('sales.send-whatsapp', $sale), ['phone' => '9876543210'])
            ->assertForbidden();

        // Fewer than 10 digits (even with punctuation padding length > 10) fails validation
        $this->actingAs($salesUser)
            ->postJson(route('sales.send-whatsapp', $sale), ['phone' => '123-456-789'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        // Non-string JSON input (e.g. array) returns 422 validation error instead of 500
        $this->actingAs($salesUser)
            ->postJson(route('sales.send-whatsapp', $sale), ['phone' => ['9876543210']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        Http::assertNothingSent();

        // Valid request dispatches Wati call
        $response = $this->actingAs($salesUser)
            ->postJson(route('sales.send-whatsapp', $sale), ['phone' => '9876543210']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Message sent via WhatsApp',
            ]);

        Http::assertSent(function (HttpRequest $request) use ($sale): bool {
            $params = collect($request->data()['parameters'] ?? [])->keyBy('name');

            return $request->url() === 'https://live-server.wati.io/999/api/v1/sendTemplateMessage?whatsappNumber=919876543210'
                && str_contains((string) ($params['pdf_url']['value'] ?? ''), "/sales/{$sale->id}/public-pdf");
        });
    }

    public function test_send_whatsapp_endpoint_surfaces_wati_api_errors_gracefully(): void
    {
        Config::set('whatsapp.provider', 'wati');
        Config::set('wati.endpoint', 'https://live-server.wati.io/999');
        Config::set('wati.token', 'secret-wati-token');
        Config::set('wati.template_name', '');

        $salesUser = User::factory()->create(['role' => 'sales']);
        $sale = $this->createSampleSale(null, $salesUser);

        Http::fake([
            '*' => Http::response(['result' => false, 'info' => 'Session window closed'], 200),
        ]);

        $response = $this->actingAs($salesUser)
            ->postJson(route('sales.send-whatsapp', $sale), ['phone' => '9876543210']);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
            ]);
        $this->assertStringContainsString('Session window closed', (string) $response->json('message'));
    }

    public function test_send_whatsapp_endpoint_dispatches_meta_cloud_api_request(): void
    {
        Config::set('whatsapp.provider', 'meta');
        Config::set('whatsapp.meta.phone_number_id', '1506663495854602');
        Config::set('whatsapp.meta.access_token', 'test_token');
        Config::set('whatsapp.meta.template_name', 'billing_template');

        $salesUser = User::factory()->create(['role' => 'sales']);
        $customer = Customer::factory()->create(['name' => 'Suman Roy', 'phone' => '9876543210']);
        $sale = $this->createSampleSale($customer, $salesUser);

        Http::fake([
            'https://graph.facebook.com/v21.0/1506663495854602/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919876543210', 'wa_id' => '919876543210']],
                'messages' => [['id' => 'wamid.HBgLM...']],
            ], 200),
        ]);

        $response = $this->actingAs($salesUser)
            ->postJson(route('sales.send-whatsapp', $sale), ['phone' => '9876543210']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Invoice sent via WhatsApp.',
            ]);

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://graph.facebook.com/v21.0/1506663495854602/messages'
                && ($request->data()['messaging_product'] ?? '') === 'whatsapp';
        });
    }

    public function test_print_view_renders_no_print_top_action_bar_with_all_controls(): void
    {
        $salesUser = User::factory()->create(['role' => 'sales']);
        $customer = Customer::factory()->create([
            'name' => 'Priya Sharma',
            'phone' => '+91 98765 43210',
        ]);
        $sale = $this->createSampleSale($customer, $salesUser);

        $response = $this->actingAs($salesUser)->get(route('sales.print', $sale));

        $response->assertOk();
        $response->assertSee('<meta name="csrf-token"', false);
        $response->assertSee('.no-print', false);
        $response->assertSee('display: none !important;', false);
        $response->assertSee('#'.$sale->invoice_number);
        $response->assertSee(format_money($sale->total));
        $response->assertSee('Priya Sharma');
        $response->assertSee('value="+91 98765 43210"', false);
        $response->assertSee('Send via WhatsApp');
        $response->assertSee('Download as PDF');
        $response->assertSee(route('sales.pdf', $sale), false);
        $response->assertSee('Print');
        $response->assertSee('window.print()', false);
        $response->assertSee(route('sales.send-whatsapp', $sale), false);
    }

    public function test_pdf_and_print_handle_guest_customer_split_payment_and_discounts(): void
    {
        $salesUser = User::factory()->create(['role' => 'sales']);
        Setting::set('currency_symbol', '₹');

        $sale = Sale::create([
            'invoice_number' => 'INV.261117.7777',
            'customer_id' => null,
            'created_by' => $salesUser->id,
            'sale_date' => '2026-11-17 18:00:00',
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 550.25,
            'global_discount' => 50.25,
            'coupon_discount' => 50.00,
            'coupon_code' => 'GLOW50',
            'round_off' => -0.25,
            'total_discount' => 50.25,
            'total' => 500.00,
            'cash_received' => 500.00,
            'change' => 0,
            'payment_method' => PaymentMethod::SPLIT->value,
            'cash_amount' => 200.00,
            'bank_amount' => 300.00,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'custom_name' => 'Custom Gift Hamper',
            'is_manual' => true,
            'quantity' => 1,
            'cost_price' => 300.00,
            'unit_price' => 550.25,
            'discount' => 0,
            'final_price' => 550.25,
            'subtotal' => 550.25,
        ]);

        $printResponse = $this->actingAs($salesUser)->get(route('sales.print', $sale));
        $printResponse->assertOk();
        $printResponse->assertSee('Guest');
        $printResponse->assertSee('value=""', false);
        $printResponse->assertSee('Cash Paid');
        $printResponse->assertSee('UPI / Bank Paid');

        $pdfResponse = $this->actingAs($salesUser)->get(route('sales.pdf', $sale));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());
    }

    public function test_print_view_strips_leading_plus_from_default_country_code_prefix(): void
    {
        Config::set('wati.default_country_code', '+91');

        $salesUser = User::factory()->create(['role' => 'sales']);
        $customer = Customer::factory()->create([
            'name' => 'Kavya Rao',
            'phone' => '+91 98765 43210',
        ]);
        $sale = $this->createSampleSale($customer, $salesUser);

        $response = $this->actingAs($salesUser)->get(route('sales.print', $sale));

        $response->assertOk();
        $response->assertSee('<span class="wa-prefix">+91</span>', false);
        $response->assertDontSee('<span class="wa-prefix">++91</span>', false);
        $response->assertSee('value="+91 98765 43210"', false);
    }

    public function test_pdf_renders_single_a5_landscape_page_for_up_to_eight_items_with_all_discounts_and_split_payment(): void
    {
        $salesUser = User::factory()->create(['role' => 'sales']);
        $category = Category::create(['name' => 'Cosmetics', 'slug' => 'cosmetics-'.uniqid()]);
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Velvet Matte Lipstick',
            'selling_price' => 500.00,
        ]);
        $service = Service::create([
            'name' => 'Bridal Makeup Trial',
            'price' => 1000.00,
            'is_active' => true,
        ]);

        Setting::set('currency_symbol', '₹');
        Setting::set('store_email', 'store@example.com');

        // 1-item round-off
        $oneItemRoundOff = Sale::create([
            'invoice_number' => 'INV.261117.8101',
            'customer_id' => null,
            'created_by' => $salesUser->id,
            'sale_date' => '2026-11-17 18:00:00',
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 500.25,
            'global_discount' => 0.25,
            'round_off' => -0.25,
            'total_discount' => 0,
            'total' => 500.00,
            'cash_received' => 500.00,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH->value,
        ]);
        SaleItem::create([
            'sale_id' => $oneItemRoundOff->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 250.00,
            'unit_price' => 500.25,
            'discount' => 0,
            'final_price' => 500.25,
            'subtotal' => 500.25,
        ]);

        // 1-item coupon
        $oneItemCoupon = Sale::create([
            'invoice_number' => 'INV.261117.8102',
            'customer_id' => null,
            'created_by' => $salesUser->id,
            'sale_date' => '2026-11-17 18:05:00',
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 500.00,
            'global_discount' => 50.00,
            'coupon_discount' => 50.00,
            'coupon_code' => 'GLOW50',
            'total_discount' => 50.00,
            'total' => 450.00,
            'cash_received' => 450.00,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH->value,
        ]);
        SaleItem::create([
            'sale_id' => $oneItemCoupon->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 250.00,
            'unit_price' => 500.00,
            'discount' => 0,
            'final_price' => 500.00,
            'subtotal' => 500.00,
        ]);

        // 8-item invoice with product discount, service discount, coupon, round-off, return discount, and split payment
        $eightItemComplex = Sale::create([
            'invoice_number' => 'INV.261117.8108',
            'customer_id' => null,
            'created_by' => $salesUser->id,
            'sale_date' => '2026-11-17 18:10:00',
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 4000.25,
            'global_discount' => 100.25,
            'coupon_discount' => 100.00,
            'coupon_code' => 'FESTIVE100',
            'round_off' => -0.25,
            'return_discount' => 50.00,
            'total_discount' => 200.25,
            'total' => 3850.00,
            'cash_received' => 3900.00,
            'change' => 50.00,
            'payment_method' => PaymentMethod::SPLIT->value,
            'cash_amount' => 1850.00,
            'bank_amount' => 2050.00,
        ]);
        for ($i = 0; $i < 6; $i++) {
            SaleItem::create([
                'sale_id' => $eightItemComplex->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'cost_price' => 250.00,
                'unit_price' => 500.00,
                'discount' => 20.00,
                'final_price' => 480.00,
                'subtotal' => 480.00,
            ]);
        }
        SaleItem::create([
            'sale_id' => $eightItemComplex->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'cost_price' => 0,
            'unit_price' => 500.00,
            'discount' => 50.00,
            'final_price' => 450.00,
            'subtotal' => 450.00,
        ]);
        SaleItem::create([
            'sale_id' => $eightItemComplex->id,
            'custom_name' => 'Custom Gift Box Deluxe',
            'is_manual' => true,
            'quantity' => 1,
            'cost_price' => 100.00,
            'unit_price' => 670.25,
            'discount' => 0,
            'final_price' => 670.25,
            'subtotal' => 670.25,
        ]);

        foreach ([$oneItemRoundOff, $oneItemCoupon, $eightItemComplex] as $caseSale) {
            $caseSale->load(['customer', 'items.product.unit', 'items.service', 'creator']);
            $pdf = Pdf::loadView('sales.pdf', ['sale' => $caseSale])
                ->setPaper('a5', 'landscape')
                ->setOption('isPhpEnabled', false)
                ->setOption('isRemoteEnabled', false)
                ->setOption('isHtml5ParserEnabled', true);
            $dompdf = $pdf->getDomPDF();
            $dompdf->render();

            $this->assertSame(
                1,
                $dompdf->getCanvas()->get_page_count(),
                "Expected single A5 landscape page for invoice {$caseSale->invoice_number}."
            );
        }
    }
}
