<?php

namespace App\Http\Controllers\Api\Client;

use App\Contracts\PaymentServiceInterface;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment processing for client invoices via PayMongo.
 */
class PaymentController extends Controller
{
    public function __construct(
        private PaymentServiceInterface $paymentService
    ) {}

    /**
     * Initiate payment for an invoice. Returns a hosted checkout URL.
     * The URL is only provided to the authenticated owner of the invoice.
     */
    public function initiatePayment(int $invoiceId, Request $request): JsonResponse
    {
        $invoice = Invoice::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->where('status', 'unpaid')
            ->findOrFail($invoiceId);

        $result = $this->paymentService->createPaymentLink($invoice);

        // Store gateway reference on invoice but do NOT return it in the response
        $invoice->update([
            'payment_gateway'      => 'paymongo',
            'gateway_reference_id' => $result['gateway_reference_id'],
            'payment_url'          => $result['payment_url'],
        ]);

        AuditService::log('payment.initiated', "Payment initiated for invoice #{$invoice->id}.");

        return response()->json([
            'payment_url' => $result['payment_url'],
            'invoice_id'  => $invoice->id,
        ]);
    }

    /**
     * PayMongo webhook endpoint.
     * This route must be EXCLUDED from Sanctum authentication middleware.
     * The signature from PayMongo is validated inside the service.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('Paymongo-Signature', '');

        try {
            $event = $this->paymentService->handleWebhook($payload, $signature);
        } catch (InvalidWebhookSignatureException $e) {
            Log::warning('PayMongo webhook signature mismatch', ['ip' => $request->ip()]);
            // Return 200 to prevent PayMongo from retrying with an invalid signature
            return response()->json(['message' => 'Ignored.']);
        }

        // Find and update the invoice
        $invoice = Invoice::where('gateway_reference_id', $event['gateway_reference_id'])->first();

        if ($invoice && $event['status'] === 'success') {
            $invoice->update([
                'status'         => 'paid',
                'payment_method' => 'gcash', // PayMongo webhook includes actual method
                'paid_at'        => now(),
            ]);

            // Record the transaction for audit/reconciliation
            PaymentTransaction::create([
                'invoice_id'      => $invoice->id,
                'payment_gateway' => 'paymongo',
                'transaction_type'=> 'charge',
                'gateway_txn_id'  => $event['gateway_reference_id'],
                'gateway_response'=> $event['raw'],
                'amount'          => $invoice->amount,
                'currency'        => 'PHP',
                'status'          => 'success',
                'processed_at'    => now(),
            ]);

            AuditService::log(
                'payment.paid',
                "Invoice #{$invoice->id} marked paid via PayMongo webhook.",
                null,
                $request->ip()
            );
        }

        return response()->json(['message' => 'Webhook processed.']);
    }
}
