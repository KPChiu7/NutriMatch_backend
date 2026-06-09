<?php

namespace App\Services;

use App\Contracts\PaymentServiceInterface;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayMongo payment gateway integration for the Philippine market.
 *
 * Security notes:
 *  - Secret key is read from config('services.paymongo') only — never hardcoded.
 *  - Webhook signatures are always validated before processing.
 *  - Full gateway responses are stored in payment_transactions for audit.
 *  - payment_url is returned to the caller but never logged.
 */
class PayMongoService implements PaymentServiceInterface
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paymongo.secret_key');
        $this->baseUrl   = config('services.paymongo.base_url');
    }

    /**
     * Create a PayMongo payment link for an invoice.
     * Returns the checkout URL for the client.
     */
    public function createPaymentLink(Invoice $invoice): array
    {
        $amountCentavos = (int) round($invoice->amount * 100);

        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(config('services.paymongo.timeout', 30))
            ->post("{$this->baseUrl}/links", [
                'data' => [
                    'attributes' => [
                        'amount'      => $amountCentavos,
                        'description' => "NutriMatch Consultation Invoice #{$invoice->id}",
                        'currency'    => 'PHP',
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::error('PayMongo create link failed', [
                'invoice_id' => $invoice->id,
                'status'     => $response->status(),
                // NOTE: never log secretKey or full response body
            ]);

            throw new \RuntimeException('Payment gateway error. Please try again later.');
        }

        $data = $response->json('data');

        return [
            'payment_url'          => $data['attributes']['checkout_url'],
            'gateway_reference_id' => $data['id'],
        ];
    }

    /**
     * Validate PayMongo webhook signature and return normalized event data.
     *
     * PayMongo uses HMAC-SHA256. The raw request body is signed, not the parsed JSON.
     *
     * @throws InvalidWebhookSignatureException
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        $webhookSecret = config('services.paymongo.webhook_secret');

        if (! $this->validateSignature($payload, $signature, $webhookSecret)) {
            Log::warning('Invalid PayMongo webhook signature received.');
            throw new InvalidWebhookSignatureException('Webhook signature validation failed.');
        }

        $event = json_decode($payload, true);
        $type  = $event['data']['attributes']['type'] ?? '';

        return [
            'event_type'           => $type,
            'gateway_reference_id' => $event['data']['attributes']['data']['id'] ?? null,
            'status'               => $this->mapEventToStatus($type),
            'raw'                  => $event,
        ];
    }

    /**
     * Retrieve current payment status from PayMongo.
     */
    public function getPaymentStatus(string $gatewayReferenceId): string
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get("{$this->baseUrl}/payment_intents/{$gatewayReferenceId}");

        if ($response->failed()) {
            return 'pending';
        }

        $status = $response->json('data.attributes.status') ?? 'pending';
        return $this->mapPayMongoStatus($status);
    }

    // --------------------------------------------------------
    // Private helpers
    // --------------------------------------------------------

    /**
     * HMAC-SHA256 signature validation.
     * Uses hash_equals to prevent timing attacks.
     */
    private function validateSignature(string $payload, string $signature, string $secret): bool
    {
        $computed = hash_hmac('sha256', $payload, $secret);
        return hash_equals($computed, $signature);
    }

    private function mapEventToStatus(string $eventType): string
    {
        return match ($eventType) {
            'payment.paid'    => 'success',
            'payment.failed'  => 'failed',
            'payment.refunded'=> 'refunded',
            default           => 'pending',
        };
    }

    private function mapPayMongoStatus(string $status): string
    {
        return match ($status) {
            'succeeded' => 'success',
            'awaiting_payment_method', 'processing' => 'pending',
            'failed'    => 'failed',
            default     => 'pending',
        };
    }
}
