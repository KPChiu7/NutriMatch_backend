<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Delivery audit log for all outbound notifications across all channels.
 * Supports RA 10173 compliance and delivery debugging.
 *
 * @property string $channel email|sms|push|in_app
 * @property string $status  queued|sent|delivered|failed|bounced
 */
class NotificationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'recipient_id',
        'channel',
        'subject',
        'content',
        'status',
        'sent_at',
        'delivered_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sent_at'      => 'datetime',
            'delivered_at' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
