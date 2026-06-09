<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Centralized service for writing to the immutable RA 10173 audit trail.
 *
 * Usage:
 *   AuditService::log('ncp.finalized', 'NCP Record #5 finalized by RND');
 *   AuditService::log('user.login', null, $user->id, $request->ip());
 */
class AuditService
{
    /**
     * Write a new audit log entry.
     *
     * @param string      $action      Machine-readable action (e.g. 'user.login', 'ncp.finalized')
     * @param string|null $description Human-readable description
     * @param int|null    $userId      Override the authenticated user (for system events use null)
     * @param string|null $ipAddress   Override IP address
     */
    public static function log(
        string  $action,
        ?string $description = null,
        ?int    $userId = null,
        ?string $ipAddress = null
    ): void {
        AuditLog::create([
            'user_id'     => $userId ?? Auth::id(),
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $ipAddress ?? request()?->ip(),
        ]);
    }
}
