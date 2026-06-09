<?php

namespace App\Services;

use App\Contracts\VideoSessionServiceInterface;
use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Daily.co video consultation session service.
 *
 * Security notes:
 *  - API key is read from config('services.daily_co') only — never hardcoded.
 *  - host_url is stored on ConsultationSession but hidden from client API responses.
 *  - participant_url is the only URL returned to clients.
 */
class DailyCoVideoService implements VideoSessionServiceInterface
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('services.daily_co.api_key');
        $this->baseUrl = config('services.daily_co.base_url');
    }

    /**
     * Create a Daily.co room for the appointment.
     * Room expires automatically after the scheduled duration.
     *
     * @return array Contains 'host_url', 'participant_url', 'external_session_id'
     */
    public function createRoom(Appointment $appointment): array
    {
        // Room name: unique per appointment to prevent collisions
        $roomName    = 'nm-appt-' . $appointment->id . '-' . Str::random(8);
        $expiresAt   = $appointment->scheduled_at->addMinutes(
            $appointment->duration_minutes ?? 60
        );

        $response = Http::withToken($this->apiKey)
            ->timeout(config('services.daily_co.timeout', 15))
            ->post("{$this->baseUrl}/rooms", [
                'name'       => $roomName,
                'properties' => [
                    'exp'          => $expiresAt->timestamp,
                    'enable_chat'  => true,
                    'start_video_off' => false,
                ],
            ]);

        if ($response->failed()) {
            Log::error('Daily.co room creation failed', [
                'appointment_id' => $appointment->id,
                'status'         => $response->status(),
                // NOTE: never log apiKey
            ]);

            throw new \RuntimeException('Video session could not be created. Please try again.');
        }

        $room = $response->json();

        // host_url: RND gets the owner token link (grants host controls)
        // participant_url: client gets the regular join link
        return [
            'external_session_id' => $roomName,
            'host_url'            => $room['url'] . '?t=' . $this->generateHostToken($roomName),
            'participant_url'     => $room['url'],
        ];
    }

    /**
     * Delete a Daily.co room after the session ends.
     */
    public function deleteRoom(string $externalSessionId): bool
    {
        $response = Http::withToken($this->apiKey)
            ->delete("{$this->baseUrl}/rooms/{$externalSessionId}");

        if ($response->failed()) {
            Log::warning('Daily.co room deletion failed', ['room' => $externalSessionId]);
            return false;
        }

        return true;
    }

    // --------------------------------------------------------
    // Private helpers
    // --------------------------------------------------------

    /**
     * Generate a Daily.co meeting token granting host/owner privileges to the RND.
     */
    private function generateHostToken(string $roomName): string
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/meeting-tokens", [
                'properties' => [
                    'room_name'   => $roomName,
                    'is_owner'    => true,
                ],
            ]);

        if ($response->failed()) {
            return '';
        }

        return $response->json('token', '');
    }
}
