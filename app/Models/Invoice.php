<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Consultation invoices.
 * commission_pct and commission_amt are frozen at creation to prevent retroactive changes.
 * gateway_reference_id enables reconciliation with payment provider records.
 *
 * SECURITY: payment_url must only be returned to the invoice's client, never to third parties.
 *
 * @property string $status unpaid|paid|cancelled|refunded
 */
class Invoice extends Model
{
    public $timestamps = false;

    /**
     * payment_url is a sensitive hosted checkout URL — exclude from default serialization.
     * Return it explicitly only when the authenticated client needs to proceed with payment.
     */
    protected $hidden = ['payment_url'];

    protected $fillable = [
        'relationship_id',
        'appointment_id',
        'amount',
        'commission_pct',
        'commission_amt',
        'status',
        'payment_gateway',
        'payment_method',
        'gateway_reference_id',
        'payment_url',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:2',
            'commission_pct' => 'decimal:2',
            'commission_amt' => 'decimal:2',
            'paid_at'        => 'datetime',
            'created_at'     => 'datetime',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(RndClientRelationship::class, 'relationship_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
