<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyReminder;
use Illuminate\Console\Command;

/** Runs every minute; notifies learners whose local reminder time is now and who have not studied today. */
class SendDailyReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send the daily study reminder at each learner\'s chosen time';

    public function handle(): int
    {
        $sent = 0;
        User::query()->whereNotNull('reminder_time')->whereNotNull('onboarded_at')->chunkById(500, function ($users) use (&$sent) {
            foreach ($users as $user) {
                $local = now($user->timezone);
                if (substr((string) $user->reminder_time, 0, 5) !== $local->format('H:i')) {
                    continue;
                }
                if ($user->last_active_date?->toDateString() === $local->toDateString()) {
                    continue;
                }
                $user->notify(new DailyReminder);
                $sent++;
            }
        });
        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
