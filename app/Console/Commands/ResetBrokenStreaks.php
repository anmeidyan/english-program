<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Gamification\StreakService;
use Illuminate\Console\Command;

class ResetBrokenStreaks extends Command
{
    protected $signature = 'streaks:reset';

    protected $description = 'Reset streaks of learners who missed a day without a freeze';

    public function handle(StreakService $streaks): int
    {
        $reset = 0;
        User::query()->where('streak_current', '>', 0)->chunkById(500, function ($users) use ($streaks, &$reset) {
            foreach ($users as $user) {
                $reset += $streaks->resetBroken($user) ? 1 : 0;
            }
        });
        $this->info("Reset {$reset} streak(s).");

        return self::SUCCESS;
    }
}
