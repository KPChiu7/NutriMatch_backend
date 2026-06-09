<?php

namespace App\Contracts;

use App\Models\Appointment;

/**
 * Contract for the video consultation session service.
 * Currently backed by Daily.co.
 */
interface VideoSessionServiceInterface
{
    /**
     * Create a new video room for an appointment.
     * Returns both host and participant URLs.
     *
     * @param  Appointment $appointment
     * @return array  Contains 'host_url', 'participant_url', 'external_session_id'
     */
    public function createRoom(Appointment $appointment): array;

    /**
     * Delete a video room after a session ends.
     *
     * @param  string $externalSessionId  Provider's room name or ID
     * @return bool
     */
    public function deleteRoom(string $externalSessionId): bool;
}
