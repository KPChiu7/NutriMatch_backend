<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Video session lifecycle per appointment.
 * provider_metadata stores the full provider API response for audit.
 * host_url and participant_url are sensitive — exclude from client responses.
 *
 * @property string $session_status scheduled|active|ended|failed|cancelled
 * @property string $video_provider zoom|daily_co|jitsi|twilio_video|google_meet|other
 */
class ConsultationSession extends Model
{
    protected $fillable = [
        'appointment_id',
        'video_provider',
        'external_session_id',
        'host_url',
        'participant_url',
        'recording_url',
        'session_status',
        'session_started_at',
        'session_ended_at',
        'actual_duration_min',
        'provider_metadata',
    ];

    /**
     * Hide host_url and provider_metadata from all serializations
     * to prevent sensitive URLs and API payloads from leaking.
     */
    protected $hidden = ['host_url', 'provider_metadata'];

    protected function casts(): array
    {
        return [
            'session_started_at' => 'datetime',
            'session_ended_at'   => 'datetime',
            'provider_metadata'  => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
