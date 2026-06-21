<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\RndClientRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only resource browsing for clients.
 *
 * Visibility is derived at query time (no relationship_id or assignment
 * table exists on resources):
 *  - Resources uploaded by an admin (role=admin) are visible to every
 *    client, platform-wide.
 *  - Resources uploaded by an RND are visible only to clients who have
 *    ANY relationship with that RND (pending, active, or discharged —
 *    education material access is not gated by active-care status the
 *    way appointment booking is).
 * Only is_active=true resources are ever shown to clients.
 */
class ResourceController extends Controller
{
    /**
     * List all resources visible to the authenticated client.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type'     => ['nullable', 'in:article,pdf,video,link'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $clientId = $request->user()->id;

        $rndIds = RndClientRelationship::where('client_id', $clientId)
            ->pluck('rnd_id');

        $resources = Resource::active()
            ->with(['uploader:id,first_name,last_name,role'])
            ->where(function ($q) use ($rndIds) {
                $q->whereIn('uploaded_by', $rndIds)
                  ->orWhereHas('uploader', fn($u) => $u->where('role', 'admin'));
            })
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($resources);
    }

    /**
     * View a single resource, only if visible to this client under the
     * same rules as index().
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $clientId = $request->user()->id;

        $rndIds = RndClientRelationship::where('client_id', $clientId)
            ->pluck('rnd_id');

        $resource = Resource::active()
            ->with(['uploader:id,first_name,last_name,role'])
            ->where(function ($q) use ($rndIds) {
                $q->whereIn('uploaded_by', $rndIds)
                  ->orWhereHas('uploader', fn($u) => $u->where('role', 'admin'));
            })
            ->findOrFail($id);

        return response()->json(['resource' => $resource]);
    }
}
