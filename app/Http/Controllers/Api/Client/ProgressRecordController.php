<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\RndClientRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only progress record access for clients.
 * Clients can view their own progress history but cannot create or edit records.
 * RNDs manage all progress record creation and editing.
 */
class ProgressRecordController extends Controller
{
    /**
     * View all progress records for the authenticated client.
     * Scoped to a specific relationship.
     */
    public function index(int $relationshipId, Request $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('client_id', $request->user()->id)
            ->findOrFail($relationshipId);

        $records = $relationship->progressRecords()
            ->orderBy('record_date', 'asc')
            ->get();

        return response()->json([
            'relationship_id' => $relationshipId,
            'total'           => $records->count(),
            'records'         => $records,
        ]);
    }

    /**
     * Get the client's progress summary across ALL their relationships.
     * Useful for a client dashboard showing overall health trends.
     */
    public function myProgress(Request $request): JsonResponse
    {
        $relationships = RndClientRelationship::where('client_id', $request->user()->id)
            ->where('status', 'active')
            ->with(['progressRecords' => fn($q) => $q->orderBy('record_date', 'desc')->limit(1)])
            ->get();

        if ($relationships->isEmpty()) {
            return response()->json([
                'message'  => 'No active relationships found.',
                'progress' => [],
            ]);
        }

        $progress = $relationships->map(fn($rel) => [
            'relationship_id' => $rel->id,
            'rnd_name'        => $rel->rnd?->full_name,
            'latest_record'   => $rel->progressRecords->first(),
        ]);

        // --------------------------------------------------------
        // OPTIONAL EXTERNAL API HOOK — Health Dashboard / Wearables
        // If the client uses a health wearable or app (e.g. Fitbit,
        // Apple Health, Google Fit), you could fetch synced data here.
        //
        // Example:
        // $wearableData = app(WearableServiceInterface::class)
        //     ->getLatestReadings($request->user()->id);
        // --------------------------------------------------------

        return response()->json(['progress' => $progress]);
    }
}
