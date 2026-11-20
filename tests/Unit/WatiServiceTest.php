<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\WatiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WatiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizes_10_digit_and_domestic_numbers_with_default_country_code(): void
    {
        Config::set('wati.default_country_code', '91');
        $service = app(WatiService::class);

        $this->assertSame('919876543210', $service->normalizePhoneNumber('9876543210'));
        $this->assertSame('919876543210', $service->normalizePhoneNumber('(987) 654-3210'));
        $this->assertSame('919876543210', $service->normalizePhoneNumber('09876543210'));

        Config::set('wati.default_country_code', '44');
        $this->assertSame('447911123456', $service->normalizePhoneNumber('7911123456'));
    }

    public function test_preserves_existing_country_code_when_present(): void
    {
        Config::set('wati.default_country_code', '91');
        $service = app(WatiService::class);

        $this->assertSame('919876543210', $service->normalizePhoneNumber('+91 98765 43210'));
        $this->assertSame('14155552671', $service->normalizePhoneNumber('+1 (415) 555-2671'));
    }

    public function test_throws_invalid_argument_exception_for_too_few_digits(): void
    {
        $service = app(WatiService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->normalizePhoneNumber('123-456-789');
    }

    public function test_send_invoice_message_uses_template_endpoint_when_template_name_is_configured(): void
    {
        Config::set('wati.endpoint', 'https://live-server.wati.io/12345/');
        Config::set('wati.token', 'Bearer test-secret-token');
        Config::set('wati.template_name', 'invoice_receipt_v1');
        Config::set('wati.default_country_code', '91');
        Setting::set('currency_symbol', 'Rs.');

        $user = User::factory()->create(['role' => 'sales']);
        $customer = Customer::factory()->create([
            'name' => 'Aarti Verma',
            'phone' => '+91 98765 43210',
        ]);
        $sale = Sale::create([
            'invoice_number' => 'INV.261117.0001',
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'sale_date' => '2026-11-17 10:30:00',
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 1250.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 1250.00,
            'cash_received' => 1250.00,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        Http::fake([
            'https://live-server.wati.io/12345/api/v1/sendTemplateMessage*' => Http::response([
                'result' => true,
                'info' => 'Template queued',
            ], 200),
        ]);

        $service = app(WatiService::class);
        $result = $service->sendInvoiceMessage($sale, '9876543210');

        $this->assertTrue($result['success']);
        $this->assertSame('Message sent via WhatsApp', $result['message']);

        Http::assertSent(function (HttpRequest $request) use ($sale): bool {
            $data = $request->data();
            $params = collect($data['parameters'] ?? [])->keyBy('name');

            return $request->method() === 'POST'
                && $request->url() === 'https://live-server.wati.io/12345/api/v1/sendTemplateMessage?whatsappNumber=919876543210'
                && $request->hasHeader('Authorization', 'Bearer test-secret-token')
                && $data['template_name'] === 'invoice_receipt_v1'
                && $data['broadcast_name'] === 'invoice_INV.261117.0001'
                && ($params['customer_name']['value'] ?? null) === 'Aarti Verma'
                && ($params['invoice_number']['value'] ?? null) === 'INV.261117.0001'
                && ($params['invoice_date']['value'] ?? null) === '17 Nov 2026'
                && ($params['total_amount']['value'] ?? null) === format_money(1250.00)
                && str_contains((string) ($params['pdf_url']['value'] ?? ''), "/sales/{$sale->id}/public-pdf")
                && str_contains((string) ($params['pdf_url']['value'] ?? ''), 'signature=')
                && str_contains((string) ($params['pdf_url']['value'] ?? ''), 'expires=');
        });
    }

    public function test_send_invoice_message_uses_session_endpoint_when_template_name_is_empty(): void
    {
        Config::set('wati.endpoint', 'https://live-server.wati.io/12345');
        Config::set('wati.token', 'raw-token-value');
        Config::set('wati.template_name', '');
        Config::set('wati.default_country_code', '91');
        Setting::set('store_name', 'Glam & Glow');
        Setting::set('currency_symbol', 'Rs.');

        $user = User::factory()->create(['role' => 'sales']);
        $sale = Sale::create([
            'invoice_number' => 'INV.261117.0002',
            'customer_id' => null,
            'created_by' => $user->id,
            'sale_date' => '2026-11-17 12:00:00',
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 499.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 499.00,
            'cash_received' => 500.00,
            'change' => 1.00,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        Http::fake([
            'https://live-server.wati.io/12345/api/v1/sendSessionMessage/*' => Http::response([
                'result' => true,
                'ok' => true,
            ], 200),
        ]);

        $service = app(WatiService::class);
        $result = $service->sendInvoiceMessage($sale, '+91 91234-56789');

        $this->assertTrue($result['success']);

        Http::assertSent(function (HttpRequest $request) use ($sale): bool {
            $messageText = (string) ($request->data()['messageText'] ?? '');

            return $request->method() === 'POST'
                && $request->url() === 'https://live-server.wati.io/12345/api/v1/sendSessionMessage/919123456789'
                && $request->hasHeader('Authorization', 'Bearer raw-token-value')
                && str_contains($messageText, 'Glam & Glow')
                && str_contains($messageText, 'Guest')
                && str_contains($messageText, 'INV.261117.0002')
                && str_contains($messageText, '17 Nov 2026')
                && str_contains($messageText, format_money(499.00))
                && str_contains($messageText, "/sales/{$sale->id}/public-pdf")
                && str_contains($messageText, 'signature=');
        });
    }

    public function test_send_invoice_message_handles_missing_credentials(): void
    {
        $user = User::factory()->create(['role' => 'sales']);
        $sale = Sale::create([
            'invoice_number' => 'INV.261117.0003',
            'customer_id' => null,
            'created_by' => $user->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 100.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 100.00,
            'cash_received' => 100.00,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        Config::set('wati.endpoint', '');
        Config::set('wati.token', '');

        $result = app(WatiService::class)->sendInvoiceMessage($sale, '9876543210');

        $this->assertFalse($result['success']);
        $this->assertSame(500, $result['status']);
    }

    public function test_send_invoice_message_rejects_whitespace_endpoint_and_bearer_only_token_without_http_calls(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => 'sales']);
        $sale = Sale::create([
            'invoice_number' => 'INV.261117.0005',
            'customer_id' => null,
            'created_by' => $user->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 100.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 100.00,
            'cash_received' => 100.00,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        // Whitespace-only endpoint
        Config::set('wati.endpoint', '   ');
        Config::set('wati.token', 'valid-token');
        $whitespaceEndpointResult = app(WatiService::class)->sendInvoiceMessage($sale, '9876543210');
        $this->assertFalse($whitespaceEndpointResult['success']);
        $this->assertSame(500, $whitespaceEndpointResult['status']);

        // Bearer-only token with trailing whitespace
        Config::set('wati.endpoint', 'https://live-server.wati.io/12345');
        Config::set('wati.token', 'Bearer   ');
        $bearerOnlyResult = app(WatiService::class)->sendInvoiceMessage($sale, '9876543210');
        $this->assertFalse($bearerOnlyResult['success']);
        $this->assertSame(500, $bearerOnlyResult['status']);

        Http::assertNothingSent();
    }

    public function test_send_invoice_message_trims_trailing_whitespace_from_endpoint(): void
    {
        Config::set('wati.endpoint', 'https://live-server.wati.io/12345/ ');
        Config::set('wati.token', 'raw-token-value');
        Config::set('wati.template_name', '');
        Config::set('wati.default_country_code', '91');

        $user = User::factory()->create(['role' => 'sales']);
        $sale = Sale::create([
            'invoice_number' => 'INV.261117.0006',
            'customer_id' => null,
            'created_by' => $user->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 250.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 250.00,
            'cash_received' => 250.00,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        Http::fake([
            'https://live-server.wati.io/12345/api/v1/sendSessionMessage/*' => Http::response([
                'result' => true,
            ], 200),
        ]);

        $result = app(WatiService::class)->sendInvoiceMessage($sale, '9876543210');

        $this->assertTrue($result['success']);

        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://live-server.wati.io/12345/api/v1/sendSessionMessage/919876543210';
        });
    }

    public function test_send_invoice_message_handles_api_error_responses_and_connection_exceptions(): void
    {
        $user = User::factory()->create(['role' => 'sales']);
        $sale = Sale::create([
            'invoice_number' => 'INV.261117.0004',
            'customer_id' => null,
            'created_by' => $user->id,
            'sale_date' => now(),
            'status' => SaleStatus::COMPLETED->value,
            'subtotal' => 100.00,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 100.00,
            'cash_received' => 100.00,
            'change' => 0,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        Config::set('wati.endpoint', 'https://live-server.wati.io/12345');
        Config::set('wati.token', 'secret-token');
        Config::set('wati.template_name', 'invoice_tpl');

        Http::fake([
            '*' => Http::sequence()
                ->push(['result' => false, 'info' => 'Invalid WhatsApp number'], 200)
                ->push(['message' => 'Unauthorized token'], 401)
                ->pushFailedConnection('Connection timed out'),
        ]);

        $service = app(WatiService::class);

        // HTTP 200 with result=false
        $logicalFail = $service->sendInvoiceMessage($sale, '9876543210');
        $this->assertFalse($logicalFail['success']);
        $this->assertSame(422, $logicalFail['status']);
        $this->assertStringContainsString('Invalid WhatsApp number', $logicalFail['message']);

        // HTTP 401 Unauthorized
        $httpFail = $service->sendInvoiceMessage($sale, '9876543210');
        $this->assertFalse($httpFail['success']);
        $this->assertSame(401, $httpFail['status']);
        $this->assertStringContainsString('Unauthorized token', $httpFail['message']);

        // Connection failure
        $connFail = $service->sendInvoiceMessage($sale, '9876543210');
        $this->assertFalse($connFail['success']);
        $this->assertSame(502, $connFail['status']);
        $this->assertStringContainsString('Connection timed out', $connFail['message']);
    }
}
