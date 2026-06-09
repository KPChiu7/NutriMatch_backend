<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable RA 10173 audit trail.
 * Rows are NEVER updated or deleted — not even by administrators.
 * ON DELETE SET NULL on user_id preserves the trail when users are soft-deleted.
 */
class AuditLog extends Model
{
    /**
     * Disable updated_at — audit rows are immutable.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
