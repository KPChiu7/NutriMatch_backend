<?php

namespace App\Contracts;

use App\Models\Invoice;

/**
 * Contract for the payment gateway service.
 * Currently backed by PayMongo for the Philippine market.
 * Abstracting behind this interface allows swapping providers.
 */
interface PaymentServiceInterface
{
    /**
     * Create a hosted payment link for an invoice.
     *
     * @param  Invoice $invoice
     * @return array   Contains 'payment_url' and 'gateway_reference_id'
     */
    public function createPaymentLink(Invoice $invoice): array;

    /**
     * Verify and process an incoming webhook payload.
     * Must validate the webhook signature before processing.
     *
     * @param  string $payload    Raw JSON request body
     * @param  string $signature  Signature header from the provider
     * @return array              Normalized event data
     *
     * @throws \App\Exceptions\InvalidWebhookSignatureException
     */
    public function handleWebhook(string $payload, string $signature): array;

    /**
     * Retrieve the current status of a payment from the provider.
     *
     * @param  string $gatewayReferenceId
     * @return string  Normalized status: pending|success|failed|refunded
     */
    public function getPaymentStatus(string $gatewayReferenceId): string;
}
