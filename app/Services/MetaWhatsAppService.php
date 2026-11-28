<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class MetaWhatsAppService
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

        $countryCode = preg_replace('/\D+/', '', (string) config('whatsapp.meta.default_country_code', '91')) ?: '91';

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
     * Send an invoice message (template or text) via Meta WhatsApp Cloud API.
     *
     * @return array{success: bool, message: string, status?: int, data?: array<string, mixed>}
     */
    public function sendInvoiceMessage(Sale $sale, string $phoneNumber): array
    {
        $phoneNumberId = trim((string) config('whatsapp.meta.phone_number_id', ''));
        $rawToken = trim((string) config('whatsapp.meta.access_token', ''));
        $token = trim((string) (preg_replace('/^Bearer(?:\s+|$)/i', '', $rawToken) ?? ''));
        $templateName = trim((string) config('whatsapp.meta.template_name', 'billing_template'));
        $languageCode = trim((string) config('whatsapp.meta.language_code', 'en')) ?: 'en';
        $apiVersion = trim((string) config('whatsapp.meta.api_version', 'v21.0')) ?: 'v21.0';

        if ($phoneNumberId === '' || $token === '') {
            return [
                'success' => false,
                'message' => 'Meta WhatsApp Cloud API credentials (Phone Number ID or Access Token) are not configured.',
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

        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

        $storeName = (string) Setting::get('store_name', config('app.name'));
        $messageText = implode("\n", [
            "Hello {$customerName},",
            '',
            "Your invoice for order #{$invoiceNumber} of amount {$totalAmount} is ready.",
            '',
            "Thank you for shopping with {$storeName}!",
            '',
            "View/Download Invoice: {$pdfUrl}",
        ]);

        $textPayload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalizedPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $messageText,
            ],
        ];

        if ($templateName !== '') {
            $components = [];

            if ($templateName === 'order_final') {
                $components[] = [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $customerName],
                        ['type' => 'text', 'text' => $totalAmount],
                        ['type' => 'text', 'text' => $invoiceNumber],
                    ],
                ];
            } elseif ($templateName === 'glam_order_approved') {
                $components[] = [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $customerName],
                        ['type' => 'text', 'text' => $invoiceNumber],
                        ['type' => 'text', 'text' => $totalAmount],
                    ],
                ];
            } elseif ($templateName === 'glam_invoice_bill' || $templateName === 'glam_billing_template') {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'link' => $pdfUrl,
                                'filename' => "Invoice-{$invoiceNumber}.pdf",
                            ],
                        ],
                    ],
                ];
                $components[] = [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $customerName],
                        ['type' => 'text', 'text' => $invoiceNumber],
                        ['type' => 'text', 'text' => $totalAmount],
                    ],
                ];
            } else {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'link' => $pdfUrl,
                                'filename' => "Invoice-{$invoiceNumber}.pdf",
                            ],
                        ],
                    ],
                ];
                $components[] = [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $customerName],
                        ['type' => 'text', 'text' => $invoiceNumber],
                        ['type' => 'text', 'text' => $totalAmount],
                    ],
                ];
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $normalizedPhone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $languageCode,
                    ],
                    'components' => $components,
                ],
            ];
        } else {
            $payload = $textPayload;
        }

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->timeout(15);

            $caPath = storage_path('app/cacert.pem');
            if (file_exists($caPath)) {
                $client = $client->withOptions(['verify' => $caPath]);
            }

            $response = $client->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Invoice sent via WhatsApp.',
                    'status' => 200,
                    'data' => $response->json(),
                ];
            }

            $json = $response->json();
            $errorCode = (int) ($json['error']['code'] ?? 0);
            $metaError = $json['error']['message'] ?? $json['message'] ?? 'Meta WhatsApp API returned an error.';

            // If the configured template does not exist, is paused, or is pending review on Meta, automatically fallback to direct text invoice
            if ($templateName !== '' && (in_array($errorCode, [132000, 132001, 132015, 132016], true) || str_contains($metaError, 'Template') || str_contains($metaError, 'template'))) {
                $fallbackResponse = $client->post($url, $textPayload);

                if ($fallbackResponse->successful()) {
                    return [
                        'success' => true,
                        'message' => 'Invoice sent via WhatsApp.',
                        'status' => 200,
                        'data' => $fallbackResponse->json(),
                    ];
                }

                $json = $fallbackResponse->json();
                $metaError = $json['error']['message'] ?? $json['message'] ?? $metaError;
            }

            return [
                'success' => false,
                'message' => $metaError,
                'status' => $response->status() >= 400 && $response->status() < 600 ? $response->status() : 502,
                'data' => is_array($json) ? $json : null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to connect to Meta WhatsApp API: '.$e->getMessage(),
                'status' => 502,
            ];
        }
    }
}
