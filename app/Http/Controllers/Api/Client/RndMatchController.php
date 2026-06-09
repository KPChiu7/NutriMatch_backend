<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\RndClientRelationship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RND matching engine for clients.
 * Filters available RNDs by specialization, language, and condition match.
 */
class RndMatchController extends Controller
{
    /**
     * Search for available RNDs matching client health needs.
     *
     * Filters:
     *  - Verified and accepting new clients
     *  - Specialization keyword match
     *  - Language preference
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'specialization' => 'nullable|string|max:100',
            'language_code'  => 'nullable|string|max:10',
            'per_page'       => 'nullable|integer|min:1|max:20',
        ]);

        $rnds = User::where('role', 'rnd')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('rndProfile', fn($q) =>
                $q->where('is_verified', true)
                  ->where('available_for_new_clients', true)
            )
            ->with([
                'rndProfile:user_id,specialization,bio,consultation_fee,language_codes,prc_license_number',
                'rndProfile.languages:rnd_id,language_code,language_name',
            ])
            ->when($request->specialization, fn($q) =>
                $q->whereHas('rndProfile', fn($r) =>
                    $r->where('specialization', 'like', "%{$request->specialization}%")
                )
            )
            ->when($request->language_code, fn($q) =>
                $q->whereHas('rndProfile', fn($r) =>
                    $r->whereJsonContains('language_codes', $request->language_code)
                )
            )
            ->select('id', 'first_name', 'last_name', 'profile_photo')
            ->paginate($request->per_page ?? 10);

        return response()->json($rnds);
    }

    /**
     * View a specific RND's public profile.
     */
    public function show(int $rndId): JsonResponse
    {
        $rnd = User::where('role', 'rnd')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('rndProfile', fn($q) =>
                $q->where('is_verified', true)
            )
            ->with([
                'rndProfile:user_id,specialization,bio,consultation_fee,language_codes,prc_expiry_date',
                'rndProfile.languages',
                'rndProfile.availabilitySchedules' => fn($q) => $q->active(),
            ])
            ->select('id', 'first_name', 'last_name', 'profile_photo')
            ->findOrFail($rndId);

        // Compute average rating from reviews
        $avgRating = \App\Models\Review::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $rndId)
            )
            ->avg('rating');

        return response()->json([
            'rnd'        => $rnd,
            'avg_rating' => $avgRating ? round($avgRating, 1) : null,
        ]);
    }

    /**
     * Send a relationship request to an RND.
     */
    public function requestRelationship(int $rndId, Request $request): JsonResponse
    {
        $rnd = User::where('role', 'rnd')
            ->where('is_active', true)
            ->whereHas('rndProfile', fn($q) =>
                $q->where('is_verified', true)
                  ->where('available_for_new_clients', true)
            )
            ->findOrFail($rndId);

        // Prevent duplicate relationship requests
        $existing = RndClientRelationship::where('rnd_id', $rndId)
            ->where('client_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have a relationship with this RND.',
                'status'  => $existing->status,
            ], 422);
        }

        $relationship = RndClientRelationship::create([
            'rnd_id'    => $rndId,
            'client_id' => $request->user()->id,
            'status'    => 'pending',
        ]);

        return response()->json([
            'message'      => 'Relationship request sent. Awaiting RND acceptance.',
            'relationship' => $relationship,
        ], 201);
    }
}
