<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Contracts\VideoSessionServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ConsultationSession;
use App\Models\RndClientRelationship;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RND appointment management — scoped strictly to the authenticated RND's relationships.
 */
class AppointmentController extends Controller
{
    public function __construct(
        private VideoSessionServiceInterface $videoService
    ) {}

    /**
     * List all appointments for the authenticated RND.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|in:pending,confirmed,completed,cancelled',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $appointments = Appointment::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->with(['relationship.client', 'preConsultationScreening', 'consultationSession'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('scheduled_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($appointments);
    }

    /**
     * View a single appointment (only if it belongs to this RND).
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->with([
                'relationship.client.clientProfile',
                'relationship.client.clientHealthProfile',
                'preConsultationScreening',
                'ncpRecord',
                'consultationSession',
                'invoice',
            ])
            ->findOrFail($id);

        return response()->json(['appointment' => $appointment]);
    }

    /**
     * Confirm a pending appointment. For video appointments, creates the Daily.co room.
     */
    public function confirm(int $id, Request $request): JsonResponse
    {
        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->where('status', 'pending')
            ->findOrFail($id);

        $appointment->update(['status' => 'confirmed']);

        // Create video room for video-type appointments
        if ($appointment->type === 'video') {
            try {
                $session = $this->videoService->createRoom($appointment);

                ConsultationSession::create([
                    'appointment_id'      => $appointment->id,
                    'video_provider'      => 'daily_co',
                    'external_session_id' => $session['external_session_id'],
                    'host_url'            => $session['host_url'],       // Hidden from client responses
                    'participant_url'     => $session['participant_url'],
                    'session_status'      => 'scheduled',
                ]);

                // Store participant URL on appointment for client access
                $appointment->update([
                    'video_session_url' => $session['participant_url'],
                    'meeting_id'        => $session['external_session_id'],
                ]);
            } catch (\RuntimeException $e) {
                // Revert confirmation if video room creation fails
                $appointment->update(['status' => 'pending']);
                return response()->json(['message' => $e->getMessage()], 503);
            }
        }

        AuditService::log('appointment.confirmed', "RND confirmed appointment #{$appointment->id}.");

        return response()->json(['message' => 'Appointment confirmed.', 'appointment' => $appointment->fresh()]);
    }

    /**
     * Mark an appointment as completed.
     */
    public function complete(int $id, Request $request): JsonResponse
    {
        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->where('status', 'confirmed')
            ->findOrFail($id);

        $appointment->update(['status' => 'completed']);

        // End the consultation session if active
        $appointment->consultationSession?->update([
            'session_status'    => 'ended',
            'session_ended_at'  => now(),
        ]);

        AuditService::log('appointment.completed', "Appointment #{$appointment->id} marked completed.");

        return response()->json(['message' => 'Appointment marked as completed.']);
    }

    /**
     * Cancel an appointment with a reason.
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($id);

        $appointment->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
        ]);

        AuditService::log('appointment.cancelled', "RND cancelled appointment #{$appointment->id}: {$request->cancellation_reason}");

        return response()->json(['message' => 'Appointment cancelled.']);
    }
}
