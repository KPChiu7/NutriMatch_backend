<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\RndClientRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only relationship view for clients. Clients cannot accept/decline
 * their own requests — that is the RND's decision, handled in
 * App\Http\Controllers\Api\Rnd\RelationshipController. Clients can only
 * view the status of relationships they have requested.
 */
class RelationshipController extends Controller
{
    /**
     * List all relationships for the authenticated client.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|in:pending,active,discharged',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $relationships = RndClientRelationship::where('client_id', $request->user()->id)
            ->with(['rnd:id,first_name,last_name,profile_photo', 'rnd.rndProfile:user_id,specialization,consultation_fee,prc_license_number'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($relationships);
    }

    /**
     * View a single relationship (only if it belongs to this client).
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('client_id', $request->user()->id)
            ->with(['rnd.rndProfile.languages'])
            ->findOrFail($id);

        return response()->json(['relationship' => $relationship]);
    }
}
