<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Http\Controllers\Controller;
use App\Models\RndClientRelationship;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages the RND side of the RND-client relationship lifecycle.
 *
 * Relationship status flow: pending -> active -> discharged.
 * A client creates the relationship in 'pending' state via
 * POST /api/client/rnds/{rndId}/request (RndMatchController). This
 * controller is where the RND accepts, declines, or later discharges
 * that relationship. No appointment can be booked until status=active
 * (RndClientRelationship::scopeActive is used to enforce this elsewhere).
 */
class RelationshipController extends Controller
{
    /**
     * List all relationships for the authenticated RND.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|in:pending,active,discharged',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $relationships = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->with(['client:id,first_name,last_name,email,phone'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($relationships);
    }

    /**
     * View a single relationship (only if it belongs to this RND).
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->with(['client.clientProfile', 'client.clientHealthProfile'])
            ->findOrFail($id);

        return response()->json(['relationship' => $relationship]);
    }

    /**
     * Accept a pending relationship request from a client.
     * Sets status=active and stamps started_at.
     */
    public function accept(int $id, Request $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->pending()
            ->findOrFail($id);

        $relationship->update([
            'status'     => 'active',
            'started_at' => now(),
        ]);

        // OPTIONAL EXTERNAL API HOOK: email/SMS notification to client that
        // their RND request was accepted.

        AuditService::log(
            'relationship.accepted',
            "RND accepted relationship request from client #{$relationship->client_id} (relationship #{$relationship->id})."
        );

        return response()->json([
            'message'      => 'Relationship accepted. Client may now book appointments with you.',
            'relationship' => $relationship->fresh(),
        ]);
    }

    /**
     * Decline a pending relationship request from a client.
     * Sets status=discharged immediately (request never became active).
     */
    public function decline(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->pending()
            ->findOrFail($id);

        $relationship->update([
            'status'   => 'discharged',
            'ended_at' => now(),
            'notes'    => $request->reason ?? $relationship->notes,
        ]);

        // OPTIONAL EXTERNAL API HOOK: email/SMS notification to client that
        // their RND request was declined.

        AuditService::log(
            'relationship.declined',
            "RND declined relationship request from client #{$relationship->client_id} (relationship #{$relationship->id})."
        );

        return response()->json([
            'message'      => 'Relationship request declined.',
            'relationship' => $relationship->fresh(),
        ]);
    }

    /**
     * Discharge an active relationship (end ongoing care).
     * Clinical records (NCP, progress, messages) remain intact via RESTRICT
     * FKs — only the relationship status changes, blocking future bookings.
     */
    public function discharge(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->active()
            ->findOrFail($id);

        $relationship->update([
            'status'   => 'discharged',
            'ended_at' => now(),
            'notes'    => $request->reason ?? $relationship->notes,
        ]);

        // OPTIONAL EXTERNAL API HOOK: email notification to client that
        // their care relationship has ended.

        AuditService::log(
            'relationship.discharged',
            "RND discharged relationship with client #{$relationship->client_id} (relationship #{$relationship->id})."
        );

        return response()->json([
            'message'      => 'Relationship discharged. Client can no longer book new appointments with you.',
            'relationship' => $relationship->fresh(),
        ]);
    }
}
