<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class WatiService
{
    public function normalizePhoneNumber(string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', trim($phoneNumber)) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) < 10) {
            throw new \InvalidArgumentException('Phone number must contain at least 10 digits.');
        }

        $countryCode = preg_replace('/\D+/', '', (string) config('wati.default_country_code', '91')) ?: '91';

        if (strlen($digits) === 10) {
            return $countryCode.$digits;
        }

        return $digits;
    }

    public function generatePublicPdfUrl(Sale $sale): string
    {
        return URL::temporarySignedRoute('sales.public-pdf', now()->addDays(7), ['sale' => $sale->id]);
    }

    /**
     * Send an invoice message (template or session) via Wati.io API.
     *
     * @return array{success: bool, message: string, status?: int, data?: array<string, mixed>}
     */
    public function sendInvoiceMessage(Sale $sale, string $phoneNumber): array
    {
        $endpoint = rtrim(trim((string) config('wati.endpoint', '')), '/');
        $rawToken = trim((string) config('wati.token', ''));
        $token = trim((string) (preg_replace('/^Bearer(?:\s+|$)/i', '', $rawToken) ?? ''));
        $templateName = trim((string) config('wati.template_name', ''));

        if ($endpoint === '' || $token === '') {
            return [
                'success' => false,
                'message' => 'Wati.io API credentials are not configured.',
                'status' => 500,
            ];
        }

        try {
            $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 422,
            ];
        }

        $sale->loadMissing('customer');

        $customerName = $sale->customer?->name ?? 'Guest';
        $invoiceNumber = (string) $sale->invoice_number;
        $invoiceDate = $sale->sale_date ? $sale->sale_date->format('d M Y') : now()->format('d M Y');
        $totalAmount = format_money($sale->total);
        $pdfUrl = $this->generatePublicPdfUrl($sale);

        $client = Http::withToken($token)
            ->acceptJson()
            ->timeout(15);

        $caPath = storage_path('app/cacert.pem');
        if (file_exists($caPath)) {
            $client = $client->withOptions(['verify' => $caPath]);
        }

        try {
            if ($templateName !== '') {
                $url = "{$endpoint}/api/v1/sendTemplateMessage?whatsappNumber={$normalizedPhone}";
                $payload = [
                    'template_name' => $templateName,
                    'broadcast_name' => 'invoice_'.$invoiceNumber,
                    'parameters' => [
                        // Named variables matching template
                        ['name' => 'name', 'value' => $customerName],
                        ['name' => 'customer_name', 'value' => $customerName],
                        ['name' => 'amount', 'value' => $totalAmount],
                        ['name' => 'total_amount', 'value' => $totalAmount],
                        ['name' => 'order_number', 'value' => $invoiceNumber],
                        ['name' => 'invoice_number', 'value' => $invoiceNumber],
                        ['name' => 'invoice_date', 'value' => $invoiceDate],
                        ['name' => 'link', 'value' => $pdfUrl],
                        ['name' => 'pdf_url', 'value' => $pdfUrl],

                        // Numbered fallback variables ({{1}}, {{2}}, {{3}}, {{4}})
                        ['name' => '1', 'value' => $customerName],
                        ['name' => '2', 'value' => $totalAmount],
                        ['name' => '3', 'value' => $invoiceNumber],
                        ['name' => '4', 'value' => $pdfUrl],
                    ],
                ];

                $response = $client->post($url, $payload);
            } else {
                $storeName = (string) Setting::get('store_name', config('app.name'));
                $messageText = implode("\n", [
                    "Thank you for shopping with {$storeName}!",
                    "Customer: {$customerName}",
                    "Invoice #: {$invoiceNumber}",
                    "Date: {$invoiceDate}",
                    "Total: {$totalAmount}",
                    "Download Invoice PDF: {$pdfUrl}",
                ]);

                $url = "{$endpoint}/api/v1/sendSessionMessage/{$normalizedPhone}";
                $response = $client->post($url, [
                    'messageText' => $messageText,
                ]);
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Failed to reach Wati.io API: '.$e->getMessage(),
                'status' => 502,
            ];
        }

        $json = $response->json();
        $isLogicalFailure = is_array($json) && (
            (array_key_exists('result', $json) && $json['result'] === false)
            || (array_key_exists('ok', $json) && $json['ok'] === false)
        );

        if ($response->failed() || $isLogicalFailure) {
            $apiError = is_array($json) ? ($json['info'] ?? $json['message'] ?? $json['error'] ?? null) : null;
            $detail = is_string($apiError) && trim($apiError) !== ''
                ? trim($apiError)
                : (trim($response->body()) !== '' ? trim($response->body()) : 'HTTP '.$response->status());

            $status = $response->failed() ? ($response->status() ?: 502) : 422;

            return [
                'success' => false,
                'message' => 'Wati.io API error: '.$detail,
                'status' => $status,
            ];
        }

        return [
            'success' => true,
            'message' => 'Message sent via WhatsApp',
            'data' => is_array($json) ? $json : [],
        ];
    }
}
