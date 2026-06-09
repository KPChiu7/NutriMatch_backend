<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Central FK hub for all clinical activity.
 * FKs use RESTRICT to prevent accidental destruction of clinical records.
 * Use soft-delete on users instead of hard deletes.
 *
 * @property string $status pending|active|discharged
 */
class RndClientRelationship extends Model
{
    protected $fillable = [
        'rnd_id',
        'client_id',
        'status',
        'started_at',
        'ended_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }

    // --------------------------------------------------------
    // Relationships
    // --------------------------------------------------------

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'relationship_id');
    }

    public function ncpRecords(): HasMany
    {
        return $this->hasMany(NcpRecord::class, 'relationship_id');
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(ProgressRecord::class, 'relationship_id');
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class, 'relationship_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'relationship_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'relationship_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'relationship_id');
    }

    // --------------------------------------------------------
    // Scopes
    // --------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
