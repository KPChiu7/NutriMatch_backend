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
 * Process due reminders every minute.
 * Queries the composite index (is_sent, send_at) for efficiency.
 *
 * FIX (this session): previously used Reminder::pending(), which only
 * checks is_sent=false and ignores send_at entirely — meaning a reminder
 * scheduled for next week would have been marked sent on the very next
 * scheduler tick. Now uses Reminder::due(), which additionally requires
 * send_at <= now(), so only reminders that have actually come due get
 * processed.
 */
Schedule::call(function () {
    $due = Reminder::due()->with('client')->get();

    foreach ($due as $reminder) {
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
