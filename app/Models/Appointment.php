<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Consultation appointments across all modalities (in-person, video, chat).
 *
 * @property string $status pending|confirmed|completed|cancelled
 * @property string $type   in_person|video|chat
 */
class Appointment extends Model
{
    protected $fillable = [
        'relationship_id',
        'scheduled_at',
        'type',
        'status',
        'duration_minutes',
        'video_session_url',
        'meeting_id',
        'cancellation_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    // --------------------------------------------------------
    // Relationships
    // --------------------------------------------------------

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(RndClientRelationship::class, 'relationship_id');
    }

    public function consultationSession(): HasOne
    {
        return $this->hasOne(ConsultationSession::class);
    }

    public function ncpRecord(): HasOne
    {
        return $this->hasOne(NcpRecord::class);
    }

    public function preConsultationScreening(): HasOne
    {
        return $this->hasOne(PreConsultationScreening::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    // --------------------------------------------------------
    // Scopes
    // --------------------------------------------------------

    public function scopeUpcoming($query)
    {
        return $query
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('scheduled_at', '>', now());
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
