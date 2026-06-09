<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores health context required for the RND-client matching engine
 * and the NCP module. Separated from client_profiles to allow
 * independent updates without altering demographic data.
 *
 * All JSON fields store PHP arrays automatically via casting.
 */
class ClientHealthProfile extends Model
{
    protected $fillable = [
        'user_id',
        'medical_conditions',
        'allergies',
        'dietary_restrictions',
        'health_goals',
        'religion',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'medical_conditions'   => 'array',
            'allergies'            => 'array',
            'dietary_restrictions' => 'array',
            'health_goals'         => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
