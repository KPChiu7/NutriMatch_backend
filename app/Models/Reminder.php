<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scheduled client notifications. Processed by the Laravel scheduler.
 * Composite index on (is_sent, send_at) optimizes scheduler queries.
 */
class Reminder extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'title',
        'message',
        'type',
        'send_at',
        'is_sent',
    ];

    protected function casts(): array
    {
        return [
            'send_at'    => 'datetime',
            'is_sent'    => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function scopePending($query)
    {
        return $query->where('is_sent', false)->where('send_at', '<=', now());
    }
}
