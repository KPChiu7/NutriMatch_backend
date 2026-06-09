<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\RndClientRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-platform messaging between RND and client.
 * Scoped to active relationships only.
 */
class MessageController extends Controller
{
    /**
     * Get messages for a relationship (paginated, newest last).
     */
    public function index(int $relationshipId, Request $request): JsonResponse
    {
        $this->authorizeRelationship($relationshipId, $request->user()->id);

        $messages = Message::where('relationship_id', $relationshipId)
            ->whereNull('deleted_at')
            ->with('sender:id,first_name,last_name,role,profile_photo')
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        // Mark unread messages as read for the current user
        Message::where('relationship_id', $relationshipId)
            ->where('sender_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json($messages);
    }

    /**
     * Send a text message.
     */
    public function store(int $relationshipId, Request $request): JsonResponse
    {
        $request->validate([
            'message'      => 'required|string|max:5000',
            'message_type' => 'nullable|in:text,file,image',
        ]);

        $this->authorizeRelationship($relationshipId, $request->user()->id);

        $message = Message::create([
            'relationship_id' => $relationshipId,
            'sender_id'       => $request->user()->id,
            'message'         => $request->message,
            'message_type'    => $request->message_type ?? 'text',
        ]);

        $message->load('sender:id,first_name,last_name,role,profile_photo');

        return response()->json(['message' => $message], 201);
    }

    /**
     * Soft-delete a message (RA 10173 — message content is retained in DB).
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $message = Message::where('sender_id', $request->user()->id)
            ->findOrFail($id);

        $message->delete(); // SoftDeletes trait — sets deleted_at

        return response()->json(['message' => 'Message deleted.']);
    }

    // --------------------------------------------------------
    // Private helpers
    // --------------------------------------------------------

    /**
     * Ensure the authenticated user is a participant in the relationship.
     */
    private function authorizeRelationship(int $relationshipId, int $userId): void
    {
        RndClientRelationship::where('id', $relationshipId)
            ->where(fn($q) =>
                $q->where('rnd_id', $userId)->orWhere('client_id', $userId)
            )
            ->firstOrFail();
    }
}
