<?php

namespace App\Services;

use App\Models\Sale;

class WhatsAppService
{
    public function __construct(
        protected MetaWhatsAppService $metaService,
        protected WatiService $watiService
    ) {}

    /**
     * Send an invoice message via the configured WhatsApp provider (Meta Cloud API or Wati).
     *
     * @return array{success: bool, message: string, status?: int, data?: array<string, mixed>}
     */
    public function sendInvoiceMessage(Sale $sale, string $phoneNumber): array
    {
        $provider = strtolower((string) config('whatsapp.provider', 'meta'));

        // If explicitly set to wati, or if Wati is configured and Meta is not
        if ($provider === 'wati') {
            return $this->watiService->sendInvoiceMessage($sale, $phoneNumber);
        }

        $metaToken = trim((string) config('whatsapp.meta.access_token', ''));
        $metaPhoneId = trim((string) config('whatsapp.meta.phone_number_id', ''));

        $watiToken = trim((string) config('wati.token', ''));
        $watiEndpoint = trim((string) config('wati.endpoint', ''));

        // If Wati is configured with endpoint & token, and provider is not forced to meta
        if ($provider !== 'meta' && $watiToken !== '' && $watiEndpoint !== '') {
            return $this->watiService->sendInvoiceMessage($sale, $phoneNumber);
        }

        // If Meta credentials are set, use Meta
        if ($metaToken !== '' && $metaPhoneId !== '') {
            return $this->metaService->sendInvoiceMessage($sale, $phoneNumber);
        }

        // Fallback to Wati if Meta is not set
        if ($watiToken !== '' && $watiEndpoint !== '') {
            return $this->watiService->sendInvoiceMessage($sale, $phoneNumber);
        }

        return $this->metaService->sendInvoiceMessage($sale, $phoneNumber);
    }
}
