<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Http\Controllers\Controller;
use App\Models\RndClientRelationship;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Progress record management for RNDs.
 * Longitudinal outcome tracking per RND-client relationship.
 *
 * Also exposes a read-only endpoint for clients (via ClientProgressController).
 */
class ProgressRecordController extends Controller
{
    /**
     * List all progress records for a relationship.
     * Ordered oldest-first to support charting/graphing on the frontend.
     */
    public function index(int $relationshipId, Request $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->findOrFail($relationshipId);

        $records = $relationship->progressRecords()
            ->orderBy('record_date', 'asc')
            ->get();

        // --------------------------------------------------------
        // OPTIONAL EXTERNAL API HOOK — Charting / Health Platform
        // If you integrate with an external health data platform
        // (e.g. Philippine Health Information Exchange, FHIR API),
        // you would fetch additional longitudinal data here and
        // merge it with $records before returning.
        //
        // Example:
        // $externalData = app(HealthPlatformServiceInterface::class)
        //     ->getPatientHistory($relationship->client_id);
        // --------------------------------------------------------

        return response()->json([
            'relationship_id' => $relationshipId,
            'total'           => $records->count(),
            'records'         => $records,
        ]);
    }

    /**
     * View a single progress record.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $record = \App\Models\ProgressRecord::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->findOrFail($id);

        return response()->json(['record' => $record]);
    }

    /**
     * Log a new progress record for a client.
     */
    public function store(int $relationshipId, Request $request): JsonResponse
    {
        $request->validate([
            'record_date'   => ['required', 'date', 'before_or_equal:today'],
            'weight_kg'     => ['nullable', 'numeric', 'min:1', 'max:500'],
            'blood_pressure'=> ['nullable', 'string', 'max:20'],
            'blood_glucose' => ['nullable', 'numeric', 'min:0'],
            'hba1c'         => ['nullable', 'numeric', 'min:0', 'max:20'],
            'adherence_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'client_notes'  => ['nullable', 'string', 'max:3000'],
            'rnd_notes'     => ['nullable', 'string', 'max:3000'],
        ]);

        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($relationshipId);

        // Prevent duplicate records on the same date
        $existing = $relationship->progressRecords()
            ->whereDate('record_date', $request->record_date)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A progress record already exists for this date. Use update instead.',
                'record'  => $existing,
            ], 422);
        }

        $record = $relationship->progressRecords()->create([
            'record_date'   => $request->record_date,
            'weight_kg'     => $request->weight_kg,
            'blood_pressure'=> $request->blood_pressure,
            'blood_glucose' => $request->blood_glucose,
            'hba1c'         => $request->hba1c,
            'adherence_pct' => $request->adherence_pct,
            'client_notes'  => $request->client_notes,
            'rnd_notes'     => $request->rnd_notes,
        ]);

        // --------------------------------------------------------
        // OPTIONAL EXTERNAL API HOOK — SMS / Push Notification
        // Notify client that a new progress entry has been logged.
        //
        // Example (SMS via Semaphore or Vonage):
        // app(SmsServiceInterface::class)->send(
        //     $relationship->client->phone,
        //     "Your RND has logged your progress for {$request->record_date}."
        // );
        //
        // Example (Push via Firebase FCM):
        // app(PushNotificationServiceInterface::class)->send(
        //     $relationship->client_id,
        //     'Progress Updated',
        //     "Your RND logged your progress for {$request->record_date}."
        // );
        // --------------------------------------------------------

        AuditService::log(
            'progress.created',
            "RND #{$request->user()->id} logged progress for relationship #{$relationship->id} on {$request->record_date}."
        );

        return response()->json(['message' => 'Progress record saved.', 'record' => $record], 201);
    }

    /**
     * Update an existing progress record.
     * RNDs may update notes or correct values after logging.
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'weight_kg'     => ['nullable', 'numeric', 'min:1', 'max:500'],
            'blood_pressure'=> ['nullable', 'string', 'max:20'],
            'blood_glucose' => ['nullable', 'numeric', 'min:0'],
            'hba1c'         => ['nullable', 'numeric', 'min:0', 'max:20'],
            'adherence_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'client_notes'  => ['nullable', 'string', 'max:3000'],
            'rnd_notes'     => ['nullable', 'string', 'max:3000'],
        ]);

        $record = \App\Models\ProgressRecord::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->findOrFail($id);

        $record->update($request->only([
            'weight_kg', 'blood_pressure', 'blood_glucose',
            'hba1c', 'adherence_pct', 'client_notes', 'rnd_notes',
        ]));

        AuditService::log('progress.updated', "Progress record #{$id} updated by RND #{$request->user()->id}.");

        return response()->json(['message' => 'Progress record updated.', 'record' => $record->fresh()]);
    }

    /**
     * Delete a progress record.
     * Allowed only by the RND who logged it.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $record = \App\Models\ProgressRecord::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->findOrFail($id);

        $record->delete();

        AuditService::log('progress.deleted', "Progress record #{$id} deleted by RND #{$request->user()->id}.");

        return response()->json(['message' => 'Progress record deleted.']);
    }

    /**
     * Get a progress summary (latest values + trends) for the relationship.
     * Used for the NCP Monitoring phase dashboard card.
     */
    public function summary(int $relationshipId, Request $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->findOrFail($relationshipId);

        $records = $relationship->progressRecords()
            ->orderBy('record_date', 'asc')
            ->get(['record_date', 'weight_kg', 'blood_glucose', 'hba1c', 'adherence_pct']);

        if ($records->isEmpty()) {
            return response()->json([
                'message' => 'No progress records found for this relationship.',
                'summary' => null,
            ]);
        }

        $latest = $records->last();
        $first  = $records->first();

        // Simple trend: positive = gained, negative = lost
        $weightChange = ($latest->weight_kg && $first->weight_kg)
            ? round($latest->weight_kg - $first->weight_kg, 2)
            : null;

        $avgAdherence = $records->whereNotNull('adherence_pct')->avg('adherence_pct');

        return response()->json([
            'summary' => [
                'total_records'     => $records->count(),
                'first_record_date' => $first->record_date,
                'latest_record_date'=> $latest->record_date,
                'latest_weight_kg'  => $latest->weight_kg,
                'weight_change_kg'  => $weightChange,
                'latest_blood_glucose' => $latest->blood_glucose,
                'latest_hba1c'      => $latest->hba1c,
                'avg_adherence_pct' => $avgAdherence ? round($avgAdherence, 1) : null,
            ],
            'chart_data' => $records->map(fn($r) => [
                'date'          => $r->record_date,
                'weight_kg'     => $r->weight_kg,
                'blood_glucose' => $r->blood_glucose,
                'hba1c'         => $r->hba1c,
                'adherence_pct' => $r->adherence_pct,
            ]),
        ]);
    }
}
