<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Contracts\VideoSessionServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ConsultationSession;
use App\Models\Invoice;
use App\Models\RndClientRelationship;
use App\Models\SystemSetting;
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

        $appointments = Appointment::whereHas(
            'relationship',
            fn($q) =>
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
        $appointment = Appointment::whereHas(
            'relationship',
            fn($q) =>
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
     *
     * Business rule (added this session): the client MUST have submitted
     * their pre-consultation screening before the RND is allowed to
     * confirm. This is checked first, before the appointment status is
     * touched or any video room is created.
     */
    public function confirm(int $id, Request $request): JsonResponse
    {
        $appointment = Appointment::whereHas(
            'relationship',
            fn($q) =>
            $q->where('rnd_id', $request->user()->id)
        )
            ->where('status', 'pending')
            ->findOrFail($id);

        // NOTE: business rule — RND cannot confirm until the client has
        // submitted their pre-consultation screening for this appointment.
        if (! $appointment->preConsultationScreening) {
            return response()->json([
                'message' => 'Client has not yet submitted their pre-consultation screening.',
            ], 422);
        }

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
     *
     * Business rule (added this session): completing an appointment
     * auto-creates an Invoice for the relationship. The commission
     * percentage is read from system_settings key
     * 'billing.default_commission_pct' and frozen onto the invoice row
     * at creation time — it is NOT retroactively recalculated if the
     * setting changes later. Invoice amount is taken from the RND's
     * consultation_fee on rnd_profiles at the time of completion.
     */
    public function complete(int $id, Request $request): JsonResponse
    {
        $appointment = Appointment::whereHas(
            'relationship',
            fn($q) =>
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

        $invoice = $this->createInvoiceForAppointment($appointment);

        AuditService::log(
            'appointment.completed',
            "Appointment #{$appointment->id} marked completed. Invoice #{$invoice->id} auto-created."
        );

        // OPTIONAL EXTERNAL API HOOK: email notification to client prompting payment.

        return response()->json([
            'message'     => 'Appointment marked as completed.',
            'appointment' => $appointment->fresh(),
            'invoice'     => $invoice,
        ]);
    }

    /**
     * Cancel an appointment with a reason.
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        $appointment = Appointment::whereHas(
            'relationship',
            fn($q) =>
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

    /**
     * Build and persist the Invoice for a just-completed appointment.
     * Commission percentage is read from system_settings and frozen onto
     * the invoice row. Amount is taken from the RND's current
     * consultation_fee on their rnd_profiles record.
     */
    private function createInvoiceForAppointment(Appointment $appointment): Invoice
    {
        $relationship = $appointment->relationship()->with('rnd.rndProfile')->first();

        $commissionPct = (float) SystemSetting::getValue('billing.default_commission_pct', 10.00);

        $amount = (float) ($relationship->rnd->rndProfile->consultation_fee ?? 0);
        $commissionAmt = round($amount * ($commissionPct / 100), 2);

        return Invoice::create([
            'relationship_id' => $relationship->id,
            'appointment_id'  => $appointment->id,
            'amount'          => $amount,
            'commission_pct'  => $commissionPct,
            'commission_amt'  => $commissionAmt,
            'status'          => 'unpaid',
        ]);
    }
}
