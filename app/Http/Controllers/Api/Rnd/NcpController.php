<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rnd\NcpRecordRequest;
use App\Models\NcpRecord;
use App\Models\RndClientRelationship;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NCP (Nutrition Care Process) record management for RNDs.
 * All 4 phases: Assessment → Diagnosis (PES) → Intervention → Monitoring.
 *
 * Security: Only the RND of the relationship may create/edit NCP records.
 * Once finalized (status=completed), records are immutable.
 */
class NcpController extends Controller
{
    /**
     * List NCP records for a specific relationship.
     */
    public function index(int $relationshipId, Request $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->findOrFail($relationshipId);

        $records = $relationship->ncpRecords()
            ->with('appointment')
            ->orderBy('encounter_date', 'desc')
            ->get();

        return response()->json(['ncp_records' => $records]);
    }

    /**
     * View a single NCP record.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $record = NcpRecord::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->with(['relationship.client', 'appointment'])
            ->findOrFail($id);

        return response()->json(['ncp_record' => $record]);
    }

    /**
     * Create a new NCP record (always starts as draft).
     */
    public function store(NcpRecordRequest $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($request->relationship_id);

        $record = $relationship->ncpRecords()->create(
            array_merge($request->validated(), ['status' => 'draft'])
        );

        AuditService::log('ncp.created', "NCP Record #{$record->id} created for relationship #{$relationship->id}.");

        return response()->json(['ncp_record' => $record], 201);
    }

    /**
     * Update a draft NCP record.
     * Completed records are immutable.
     */
    public function update(int $id, NcpRecordRequest $request): JsonResponse
    {
        $record = NcpRecord::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->where('status', 'draft')
            ->findOrFail($id);

        $record->update($request->validated());

        AuditService::log('ncp.updated', "NCP Record #{$record->id} updated.");

        return response()->json(['ncp_record' => $record->fresh()]);
    }

    /**
     * Finalize an NCP record. Once completed, it cannot be edited.
     * Requires all 4 phases to be filled before finalizing.
     */
    public function finalize(int $id, Request $request): JsonResponse
    {
        $record = NcpRecord::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->where('status', 'draft')
            ->findOrFail($id);

        // Validate minimum required fields before finalizing
        $requiredFields = ['weight_kg', 'height_cm', 'pes_problem', 'diet_prescription'];
        foreach ($requiredFields as $field) {
            if (empty($record->{$field})) {
                return response()->json([
                    'message' => "Cannot finalize: '{$field}' is required before completing.",
                ], 422);
            }
        }

        $record->update(['status' => 'completed']);

        AuditService::log('ncp.finalized', "NCP Record #{$record->id} finalized by RND #{$request->user()->id}.");

        return response()->json(['message' => 'NCP record has been finalized.', 'ncp_record' => $record->fresh()]);
    }
}
