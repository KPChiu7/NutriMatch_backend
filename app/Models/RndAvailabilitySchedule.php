<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Weekly availability windows for the RND.
 * Enables clients and the booking system to respect RND working hours.
 *
 * day_of_week: 0=Sunday, 1=Monday … 6=Saturday
 */
class RndAvailabilitySchedule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rnd_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'is_available'   => 'boolean',
            'effective_from' => 'date',
            'effective_to'   => 'date',
        ];
    }

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->where('is_available', true)
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
            });
    }
}
