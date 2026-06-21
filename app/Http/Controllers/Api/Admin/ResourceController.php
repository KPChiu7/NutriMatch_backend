<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rnd\ResourceRequest;
use App\Models\Resource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-side resource management.
 *
 * index()/show() give the admin full platform oversight — every resource
 * regardless of uploader, unlike Api\Rnd\ResourceController which is
 * scoped to the RND's own uploads.
 *
 * store() uploads under the admin's own id; since uploaded_by belongs to
 * a role=admin user, Api\Client\ResourceController treats it as visible
 * to ALL clients platform-wide (not scoped to any one RND's clients).
 *
 * update()/destroy() let an admin moderate ANY resource (e.g. deactivate
 * an RND's resource that violates content guidelines), not just their own.
 */
class ResourceController extends Controller
{
    /**
     * List all resources platform-wide, optionally filtered by uploader or type.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'uploaded_by' => ['nullable', 'integer', 'exists:users,id'],
            'type'        => ['nullable', 'in:article,pdf,video,link'],
            'is_active'   => ['nullable', 'boolean'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $resources = Resource::with(['uploader:id,first_name,last_name,role'])
            ->when($request->uploaded_by, fn($q) => $q->where('uploaded_by', $request->uploaded_by))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($resources);
    }

    /**
     * View any resource platform-wide.
     */
    public function show(int $id): JsonResponse
    {
        $resource = Resource::with(['uploader:id,first_name,last_name,role'])
            ->findOrFail($id);

        return response()->json(['resource' => $resource]);
    }

    /**
     * Upload a platform-wide resource (visible to all clients).
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

        AuditService::log('resource.created', "Admin uploaded platform-wide resource #{$resource->id}: \"{$resource->title}\".");

        return response()->json(['message' => 'Resource uploaded.', 'resource' => $resource], 201);
    }

    /**
     * Moderate (update) any resource platform-wide, regardless of uploader.
     */
    public function update(int $id, ResourceRequest $request): JsonResponse
    {
        $resource = Resource::findOrFail($id);

        $resource->update($request->only([
            'title', 'description', 'type', 'file_path', 'url', 'is_active',
        ]));

        AuditService::log('resource.moderated', "Admin updated resource #{$resource->id} (uploaded by user #{$resource->uploaded_by}).");

        return response()->json(['message' => 'Resource updated.', 'resource' => $resource->fresh()]);
    }

    /**
     * Deactivate any resource platform-wide (content moderation).
     */
    public function destroy(int $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);
        $resource->update(['is_active' => false]);

        AuditService::log('resource.deactivated', "Admin deactivated resource #{$resource->id} (uploaded by user #{$resource->uploaded_by}).");

        return response()->json(['message' => 'Resource deactivated.']);
    }
}
