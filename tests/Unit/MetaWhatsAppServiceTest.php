<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaWhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    private MetaWhatsAppService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MetaWhatsAppService::class);
    }

    public function test_normalizes_10_digit_and_domestic_numbers_with_default_country_code(): void
    {
        Config::set('whatsapp.meta.default_country_code', '91');

        $this->assertSame('919876543210', $this->service->normalizePhoneNumber('9876543210'));
        $this->assertSame('919876543210', $this->service->normalizePhoneNumber('09876543210'));
        $this->assertSame('919876543210', $this->service->normalizePhoneNumber('+91 98765 43210'));
    }

    public function test_send_invoice_message_via_meta_cloud_api_with_template(): void
    {
        Config::set('whatsapp.meta.phone_number_id', '10987654321');
        Config::set('whatsapp.meta.access_token', 'EAAG_test_meta_token');
        Config::set('whatsapp.meta.template_name', 'billing_template');
        Config::set('whatsapp.meta.language_code', 'en');
        Config::set('whatsapp.meta.api_version', 'v21.0');

        Http::fake([
            'https://graph.facebook.com/v21.0/10987654321/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919876543210', 'wa_id' => '919876543210']],
                'messages' => [['id' => 'wamid.HBgLM...']],
            ], 200),
        ]);

        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Kavita Das', 'phone' => '9876543210']);
        $sale = Sale::create([
            'invoice_number' => 'INV.261119.0001',
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'sale_date' => '2026-11-19 12:00:00',
            'subtotal' => 1250.00,
            'total' => 1250.00,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        $result = $this->service->sendInvoiceMessage($sale, '9876543210');

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['status']);

        Http::assertSent(function (HttpRequest $request) {
            $data = $request->data();

            return $request->url() === 'https://graph.facebook.com/v21.0/10987654321/messages'
                && $request->hasHeader('Authorization', 'Bearer EAAG_test_meta_token')
                && ($data['messaging_product'] ?? '') === 'whatsapp'
                && ($data['to'] ?? '') === '919876543210'
                && ($data['type'] ?? '') === 'template'
                && ($data['template']['name'] ?? '') === 'billing_template'
                && count($data['template']['components'][0]['parameters']) === 4;
        });
    }

    public function test_send_invoice_handles_meta_error_response(): void
    {
        Config::set('whatsapp.meta.phone_number_id', '10987654321');
        Config::set('whatsapp.meta.access_token', 'invalid_token');

        Http::fake([
            'https://graph.facebook.com/v21.0/10987654321/messages' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token.',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 401),
        ]);

        $user = User::factory()->create();
        $sale = Sale::create([
            'invoice_number' => 'INV.261119.0002',
            'created_by' => $user->id,
            'sale_date' => '2026-11-19 12:00:00',
            'subtotal' => 500.00,
            'total' => 500.00,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        $result = $this->service->sendInvoiceMessage($sale, '9876543210');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid OAuth access token.', $result['message']);
        $this->assertSame(401, $result['status']);
    }
}
