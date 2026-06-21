<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ReminderRequest;
use App\Models\Reminder;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Client-side reminder management. A client can create, view, update,
 * and cancel reminders for themselves (client_id = authenticated user).
 * This includes reminders an RND created on their behalf — the client
 * can still view/cancel those, just not impersonate the RND as creator
 * on update.
 */
class ReminderController extends Controller
{
    /**
     * List all reminders belonging to the authenticated client.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'is_sent'  => ['nullable', 'boolean'],
            'type'     => ['nullable', 'in:appointment,meal_log,medication,general'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $reminders = Reminder::where('client_id', $request->user()->id)
            ->with(['creator:id,first_name,last_name,role'])
            ->when($request->has('is_sent'), fn($q) => $q->where('is_sent', $request->boolean('is_sent')))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->orderBy('send_at', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json($reminders);
    }

    /**
     * View a single reminder belonging to the authenticated client.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $reminder = Reminder::where('client_id', $request->user()->id)
            ->with(['creator:id,first_name,last_name,role'])
            ->findOrFail($id);

        return response()->json(['reminder' => $reminder]);
    }

    /**
     * Create a reminder for the authenticated client themselves.
     */
    public function store(ReminderRequest $request): JsonResponse
    {
        $reminder = Reminder::create([
            'client_id'  => $request->user()->id,
            'created_by' => $request->user()->id,
            'title'      => $request->title,
            'message'    => $request->message,
            'type'       => $request->type,
            'send_at'    => $request->send_at,
            'is_sent'    => false,
        ]);

        AuditService::log('reminder.created', "Client created reminder #{$reminder->id}: \"{$reminder->title}\".");

        return response()->json(['message' => 'Reminder created.', 'reminder' => $reminder], 201);
    }

    /**
     * Update a reminder belonging to the authenticated client.
     * Only pending (not yet sent) reminders can be updated.
     */
    public function update(int $id, ReminderRequest $request): JsonResponse
    {
        $reminder = Reminder::where('client_id', $request->user()->id)
            ->pending()
            ->findOrFail($id);

        $reminder->update($request->only(['title', 'message', 'type', 'send_at']));

        AuditService::log('reminder.updated', "Client updated reminder #{$reminder->id}.");

        return response()->json(['message' => 'Reminder updated.', 'reminder' => $reminder->fresh()]);
    }

    /**
     * Cancel (delete) a pending reminder. Already-sent reminders are kept
     * as a record and cannot be deleted through this endpoint.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $reminder = Reminder::where('client_id', $request->user()->id)
            ->pending()
            ->findOrFail($id);

        $reminder->delete();

        AuditService::log('reminder.cancelled', "Client cancelled reminder #{$id}.");

        return response()->json(['message' => 'Reminder cancelled.']);
    }
}
