<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rnd\ReminderRequest;
use App\Models\Reminder;
use App\Models\RndClientRelationship;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RND-side reminder management. An RND may create, view, and cancel
 * reminders only for clients they have an ACTIVE relationship with —
 * a pending or discharged relationship is not enough (same gate as
 * appointment booking).
 *
 * Visibility scope: index()/show() only return reminders this RND
 * personally created (created_by = authenticated RND) for their
 * clients — not every reminder that client has, including ones the
 * client made for themselves. Use the client's own endpoints for that.
 */
class ReminderController extends Controller
{
    /**
     * List reminders the authenticated RND has created for their clients.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => ['nullable', 'integer'],
            'is_sent'   => ['nullable', 'boolean'],
            'type'      => ['nullable', 'in:appointment,meal_log,medication,general'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $reminders = Reminder::where('created_by', $request->user()->id)
            ->with(['client:id,first_name,last_name'])
            ->when($request->client_id, fn($q) => $q->where('client_id', $request->client_id))
            ->when($request->has('is_sent'), fn($q) => $q->where('is_sent', $request->boolean('is_sent')))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->orderBy('send_at', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json($reminders);
    }

    /**
     * View a single reminder created by the authenticated RND.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $reminder = Reminder::where('created_by', $request->user()->id)
            ->with(['client:id,first_name,last_name'])
            ->findOrFail($id);

        return response()->json(['reminder' => $reminder]);
    }

    /**
     * Create a reminder for a client. The client must be in an ACTIVE
     * relationship with the authenticated RND.
     */
    public function store(ReminderRequest $request): JsonResponse
    {
        $hasActiveRelationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->where('client_id', $request->client_id)
            ->active()
            ->exists();

        if (! $hasActiveRelationship) {
            return response()->json([
                'message' => 'You can only set reminders for clients you have an active relationship with.',
            ], 422);
        }

        $reminder = Reminder::create([
            'client_id'  => $request->client_id,
            'created_by' => $request->user()->id,
            'title'      => $request->title,
            'message'    => $request->message,
            'type'       => $request->type,
            'send_at'    => $request->send_at,
            'is_sent'    => false,
        ]);

        AuditService::log('reminder.created', "RND created reminder #{$reminder->id} for client #{$reminder->client_id}.");

        return response()->json(['message' => 'Reminder created.', 'reminder' => $reminder], 201);
    }

    /**
     * Update a pending reminder created by the authenticated RND.
     */
    public function update(int $id, ReminderRequest $request): JsonResponse
    {
        $reminder = Reminder::where('created_by', $request->user()->id)
            ->pending()
            ->findOrFail($id);

        $reminder->update($request->only(['title', 'message', 'type', 'send_at']));

        AuditService::log('reminder.updated', "RND updated reminder #{$reminder->id}.");

        return response()->json(['message' => 'Reminder updated.', 'reminder' => $reminder->fresh()]);
    }

    /**
     * Cancel a pending reminder created by the authenticated RND.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $reminder = Reminder::where('created_by', $request->user()->id)
            ->pending()
            ->findOrFail($id);

        $reminder->delete();

        AuditService::log('reminder.cancelled', "RND cancelled reminder #{$id}.");

        return response()->json(['message' => 'Reminder cancelled.']);
    }
}
