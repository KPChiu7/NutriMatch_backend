<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterClientRequest;
use App\Http\Requests\Auth\RegisterRndRequest;
use App\Models\ClientProfile;
use App\Models\RndProfile;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Handles authentication for all user roles.
 * Uses Laravel Sanctum token-based authentication.
 */
class AuthController extends Controller
{
    /**
     * Authenticate a user and return a Sanctum token.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)
            ->whereNull('deleted_at')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Please contact support.'],
            ]);
        }

        // Revoke existing tokens to enforce single-session policy
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        AuditService::log('user.login', "User {$user->email} logged in.", $user->id, $request->ip());

        return response()->json([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => [
                'id'         => $user->id,
                'role'       => $user->role,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
            ],
        ]);
    }

    /**
     * Register a new client account.
     * Uses new/save pattern to bypass $fillable guard for password.
     */
    public function registerClient(RegisterClientRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user             = new User();
            $user->role       = 'client';
            $user->first_name = $request->first_name;
            $user->last_name  = $request->last_name;
            $user->email      = $request->email;
            $user->password   = Hash::make($request->password);
            $user->phone      = $request->phone;
            $user->save();

            ClientProfile::create([
                'user_id'       => $user->id,
                'date_of_birth' => $request->date_of_birth,
                'sex'           => $request->sex,
                'language_code' => $request->language_code ?? 'en',
            ]);

            return $user;
        });

        AuditService::log('user.registered', "Client {$user->email} registered.", $user->id, $request->ip());

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => [
                'id'         => $user->id,
                'role'       => $user->role,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
            ],
        ], 201);
    }

    /**
     * Register a new RND account (pending admin verification).
     * Uses new/save pattern to bypass $fillable guard for password.
     */
    public function registerRnd(RegisterRndRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user             = new User();
            $user->role       = 'rnd';
            $user->first_name = $request->first_name;
            $user->last_name  = $request->last_name;
            $user->email      = $request->email;
            $user->password   = Hash::make($request->password);
            $user->phone      = $request->phone;
            $user->save();

            RndProfile::create([
                'user_id'            => $user->id,
                'prc_license_number' => $request->prc_license_number,
                'prc_expiry_date'    => $request->prc_expiry_date,
                'specialization'     => $request->specialization,
                'consultation_fee'   => $request->consultation_fee,
                'bio'                => $request->bio,
                'is_verified'        => false,
            ]);

            return $user;
        });

        AuditService::log('user.registered', "RND {$user->email} registered (pending verification).", $user->id, $request->ip());

        return response()->json([
            'message' => 'Registration submitted. Your account is pending verification by an administrator.',
            'user_id' => $user->id,
        ], 201);
    }

    /**
     * Return the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['rndProfile', 'clientProfile']);

        return response()->json(['user' => $user]);
    }

    /**
     * Revoke all tokens and log out.
     */
    public function logout(Request $request): JsonResponse
    {
        AuditService::log('user.logout', "User {$request->user()->email} logged out.");
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
