<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scheduled client notification records. Processed by the
 * reminders:process Artisan command (see App\Console\Commands\ProcessReminders),
 * which is registered on Laravel's scheduler to run periodically.
 *
 * created_by distinguishes who set the reminder: the client themselves,
 * or their RND (only permitted for clients in an active relationship —
 * enforced in Api\Rnd\ReminderController, not at the model/DB level).
 * Nullable since the creating user's account may later be deleted.
 *
 * @property string $type appointment|meal_log|medication|general
 */
class Reminder extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'created_by',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Reminders not yet sent. */
    public function scopePending($query)
    {
        return $query->where('is_sent', false);
    }

    /** Reminders due now or in the past, and not yet sent — what the scheduler processes. */
    public function scopeDue($query)
    {
        return $query->where('is_sent', false)
            ->where('send_at', '<=', now());
    }
}
