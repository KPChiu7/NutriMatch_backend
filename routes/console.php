<?php

use App\Models\Reminder;
use App\Services\AuditService;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes / Scheduled Tasks
|--------------------------------------------------------------------------
*/

/**
 * Process pending reminders every minute.
 * Queries the composite index (is_sent, send_at) for efficiency.
 */
Schedule::call(function () {
    $pending = Reminder::pending()->with('client')->get();

    foreach ($pending as $reminder) {
        // TODO: Dispatch actual notification (email/SMS/push) via notification channel
        // For now, mark as sent and log
        $reminder->update(['is_sent' => true]);

        AuditService::log(
            'reminder.sent',
            "Reminder '{$reminder->title}' sent to client #{$reminder->client_id}.",
            null
        );
    }
})->everyMinute()->name('process-reminders');

/**
 * Prune expired API cache entries daily at midnight.
 */
Schedule::call(function () {
    \App\Models\ApiCache::where('expires_at', '<', now())->delete();
})->daily()->name('prune-api-cache');
