<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demographic profile for the client role.
 * Clinical health data is stored separately in client_health_profiles.
 */
class ClientProfile extends Model
{
    protected $fillable = [
        'user_id',
        'date_of_birth',
        'sex',
        'language_code',
        'address',
        'emergency_contact',
        'emergency_phone',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Compute age in years from date_of_birth.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
