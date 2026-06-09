<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Extends users table for the RND (Registered Nutritionist-Dietitian) role.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $prc_license_number
 * @property string $specialization
 * @property array  $language_codes   JSON array of ISO 639-1 codes
 * @property float  $consultation_fee
 * @property bool   $available_for_new_clients
 * @property bool   $is_verified
 */
class RndProfile extends Model
{
    protected $fillable = [
        'user_id',
        'prc_license_number',
        'prc_expiry_date',
        'specialization',
        'language_codes',
        'bio',
        'consultation_fee',
        'available_for_new_clients',
        'is_verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'language_codes'           => 'array',
            'consultation_fee'         => 'decimal:2',
            'available_for_new_clients'=> 'boolean',
            'is_verified'              => 'boolean',
            'prc_expiry_date'          => 'date',
            'verified_at'              => 'datetime',
        ];
    }

    // --------------------------------------------------------
    // Relationships
    // --------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(RndLanguage::class, 'rnd_id', 'user_id');
    }

    public function availabilitySchedules(): HasMany
    {
        return $this->hasMany(RndAvailabilitySchedule::class, 'rnd_id', 'user_id');
    }

    // --------------------------------------------------------
    // Scopes
    // --------------------------------------------------------

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeAcceptingClients($query)
    {
        return $query->where('available_for_new_clients', true);
    }
}
