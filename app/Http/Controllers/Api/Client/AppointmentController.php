<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\BookAppointmentRequest;
use App\Models\Appointment;
use App\Models\RndClientRelationship;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Client appointment management — scoped strictly to the authenticated client.
 */
class AppointmentController extends Controller
{
    /**
     * List all appointments for the authenticated client.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|in:pending,confirmed,completed,cancelled',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $appointments = Appointment::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->with(['relationship.rnd.rndProfile', 'consultationSession', 'invoice'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('scheduled_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($appointments);
    }

    /**
     * View a single appointment (only if it belongs to this client).
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->with([
                'relationship.rnd.rndProfile',
                'preConsultationScreening',
                'invoice',
                // Only return participant_url to client — host_url is hidden in ConsultationSession model
                'consultationSession:id,appointment_id,participant_url,session_status',
            ])
            ->findOrFail($id);

        return response()->json(['appointment' => $appointment]);
    }

    /**
     * Book a new appointment with an active RND.
     */
    public function store(BookAppointmentRequest $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('client_id', $request->user()->id)
            ->where('rnd_id', $request->rnd_id)
            ->where('status', 'active')
            ->firstOrFail();

        $appointment = $relationship->appointments()->create([
            'scheduled_at'    => $request->scheduled_at,
            'type'            => $request->type,
            'duration_minutes'=> $request->duration_minutes ?? 60,
            'notes'           => $request->notes,
            'status'          => 'pending',
        ]);

        AuditService::log(
            'appointment.booked',
            "Client #{$request->user()->id} booked appointment #{$appointment->id} with RND #{$request->rnd_id}."
        );

        return response()->json(['message' => 'Appointment booked successfully.', 'appointment' => $appointment], 201);
    }

    /**
     * Cancel an appointment (only pending appointments can be cancelled by the client).
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $appointment = Appointment::whereHas('relationship', fn($q) =>
                $q->where('client_id', $request->user()->id)
            )
            ->where('status', 'pending')
            ->findOrFail($id);

        $appointment->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
        ]);

        AuditService::log('appointment.cancelled', "Client cancelled appointment #{$appointment->id}.");

        return response()->json(['message' => 'Appointment cancelled.']);
    }
}
