<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin controller for user management.
 * All routes in this controller require the 'admin' role (enforced by route middleware).
 */
class UserController extends Controller
{
    /**
     * List all users with pagination and optional role filter.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'role'     => 'nullable|in:admin,rnd,client',
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $users = User::withTrashed()
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->search, fn($q, $s) => $q->where(
                fn($q) => $q->where('first_name', 'like', "%{$s}%")
                            ->orWhere('last_name', 'like', "%{$s}%")
                            ->orWhere('email', 'like', "%{$s}%")
            ))
            ->with(['rndProfile', 'clientProfile'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($users);
    }

    /**
     * View a single user with their full profile.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::withTrashed()
            ->with(['rndProfile.languages', 'clientProfile', 'clientHealthProfile'])
            ->findOrFail($id);

        return response()->json(['user' => $user]);
    }

    /**
     * Activate or deactivate a user account.
     */
    public function toggleActive(int $id, Request $request): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from deactivating themselves
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $action = $user->is_active ? 'user.activated' : 'user.deactivated';
        AuditService::log($action, "Admin changed status of user #{$user->id} ({$user->email}).");

        return response()->json([
            'message'   => "User has been " . ($user->is_active ? 'activated' : 'deactivated') . ".",
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Verify an RND account after PRC license validation.
     */
    public function verifyRnd(int $id): JsonResponse
    {
        $user = User::where('role', 'rnd')->findOrFail($id);

        if (! $user->rndProfile) {
            return response()->json(['message' => 'RND profile not found.'], 404);
        }

        if ($user->rndProfile->is_verified) {
            return response()->json(['message' => 'RND is already verified.'], 422);
        }

        $user->rndProfile->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        AuditService::log('rnd.verified', "Admin verified RND #{$user->id} ({$user->email}).");

        return response()->json(['message' => 'RND account verified successfully.']);
    }

    /**
     * Soft-delete a user. Clinical records are preserved (FK RESTRICT).
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

        AuditService::log('user.soft_deleted', "Admin soft-deleted user #{$user->id} ({$user->email}).");

        return response()->json(['message' => 'User account has been deleted.']);
    }
}
