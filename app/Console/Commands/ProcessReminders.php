<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Manually-runnable counterpart to the scheduled reminder job in
 * routes/console.php (Schedule::call(...)->everyMinute()->name('process-reminders')).
 *
 * Both this command and the scheduler query Reminder::due() (is_sent=false
 * AND send_at <= now) and both mark matched rows is_sent=true. Running
 * both in the same minute is safe — whichever executes second simply
 * finds zero remaining due rows, since the first one already flipped
 * is_sent. No locking or coordination needed.
 *
 * Useful for:
 *  - Testing the reminder pipeline on demand without waiting for the
 *    next scheduler tick (`php artisan reminders:process`)
 *  - Manually triggering a catch-up run if the scheduler was paused
 *
 * Logs via AuditService::log('reminder.sent', ...) to match the same
 * audit action the scheduler closure uses, so the audit trail is
 * identical regardless of which path processed a given reminder.
 */
class ProcessReminders extends Command
{
    protected $signature = 'reminders:process';

    protected $description = 'Find due reminders and send them (stubbed), marking them as sent.';

    public function handle(): int
    {
        $dueReminders = Reminder::due()->with('client:id,first_name,last_name,email,phone')->get();

        if ($dueReminders->isEmpty()) {
            $this->info('No due reminders to process.');
            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($dueReminders as $reminder) {
            try {
                // OPTIONAL EXTERNAL API HOOK: send via email/SMS provider
                // (e.g. Semaphore for SMS). Stubbed until that integration
                // is wired up — is_sent still flips so the lifecycle works
                // end-to-end without a live provider.
                $reminder->update(['is_sent' => true]);

                AuditService::log(
                    'reminder.sent',
                    "Reminder '{$reminder->title}' sent to client #{$reminder->client_id}.",
                    null
                );

                $sentCount++;
            } catch (\Throwable $e) {
                Log::error('Failed to process reminder.', [
                    'reminder_id' => $reminder->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processed {$sentCount} of {$dueReminders->count()} due reminder(s).");

        return self::SUCCESS;
    }
}
