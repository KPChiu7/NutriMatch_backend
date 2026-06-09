<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * USDA and FNRI FCT API response cache.
 * Prevents rate limit exhaustion by storing responses keyed by MD5(source+params).
 * Laravel's own cache driver may also be used; this provides a DB-level audit trail.
 */
class ApiCache extends Model
{
    protected $fillable = [
        'cache_key',
        'source_api',
        'query_term',
        'response',
        'expires_at',
        'hit_count',
    ];

    protected function casts(): array
    {
        return [
            'response'   => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
