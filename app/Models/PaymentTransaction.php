<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment gateway transaction records.
 * gateway_response stores the full webhook payload for audit and reconciliation.
 *
 * SECURITY: gateway_response is hidden from API responses — it may contain
 * sensitive provider data that must not be exposed to clients.
 *
 * @property string $transaction_type charge|refund|chargeback|adjustment
 * @property string $status pending|success|failed|refunded|disputed
 */
class PaymentTransaction extends Model
{
    public $timestamps = false;

    protected $hidden = ['gateway_response'];

    protected $fillable = [
        'invoice_id',
        'payment_gateway',
        'payment_method',
        'transaction_type',
        'gateway_txn_id',
        'gateway_response',
        'amount',
        'currency',
        'status',
        'refund_amount',
        'refund_reason',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway_response' => 'array',
            'amount'           => 'decimal:2',
            'refund_amount'    => 'decimal:2',
            'processed_at'     => 'datetime',
            'created_at'       => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
