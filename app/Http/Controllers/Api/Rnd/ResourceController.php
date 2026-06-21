<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rnd\ResourceRequest;
use App\Models\Resource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RND-side resource management. An RND manages only the resources they
 * themselves uploaded (uploaded_by = authenticated RND's id). Once
 * uploaded, a resource is automatically visible to every client who has
 * any relationship with that RND — see Api\Client\ResourceController for
 * the visibility query.
 */
class ResourceController extends Controller
{
    /**
     * List all resources uploaded by the authenticated RND.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type'     => ['nullable', 'in:article,pdf,video,link'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $resources = Resource::where('uploaded_by', $request->user()->id)
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($resources);
    }

    /**
     * View a single resource (only if uploaded by this RND).
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $resource = Resource::where('uploaded_by', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['resource' => $resource]);
    }

    /**
     * Upload a new resource.
     */
    public function store(ResourceRequest $request): JsonResponse
    {
        $resource = Resource::create([
            'uploaded_by' => $request->user()->id,
            'title'       => $request->title,
            'description' => $request->description,
            'type'        => $request->type,
            'file_path'   => $request->file_path,
            'url'         => $request->url,
            'is_active'   => $request->is_active ?? true,
        ]);

        AuditService::log('resource.created', "RND uploaded resource #{$resource->id}: \"{$resource->title}\".");

        return response()->json(['message' => 'Resource uploaded.', 'resource' => $resource], 201);
    }

    /**
     * Update a resource owned by the authenticated RND.
     */
    public function update(int $id, ResourceRequest $request): JsonResponse
    {
        $resource = Resource::where('uploaded_by', $request->user()->id)
            ->findOrFail($id);

        $resource->update($request->only([
            'title', 'description', 'type', 'file_path', 'url', 'is_active',
        ]));

        AuditService::log('resource.updated', "RND updated resource #{$resource->id}.");

        return response()->json(['message' => 'Resource updated.', 'resource' => $resource->fresh()]);
    }

    /**
     * Deactivate a resource (soft state change — is_active=false hides it
     * from client visibility queries without hard-deleting the row).
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $resource = Resource::where('uploaded_by', $request->user()->id)
            ->findOrFail($id);

        $resource->update(['is_active' => false]);

        AuditService::log('resource.deactivated', "RND deactivated resource #{$resource->id}.");

        return response()->json(['message' => 'Resource deactivated.']);
    }
}
