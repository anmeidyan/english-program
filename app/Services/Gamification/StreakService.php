<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * PRD 6.2 streak: a day counts with ≥ 1 lesson, ≥ 20 flashcards or the daily speaking goal (learner's timezone).
 * One streak freeze per ISO week covers a single missed day.
 */
class StreakService
{
    public function recordActivity(User $user, ?Carbon $at = null): void
    {
        $today = $this->localDay($at ?? now(), $user);
        $last = $user->last_active_date ? Carbon::parse($user->last_active_date->toDateString()) : null;

        if ($last !== null && $last->equalTo($today)) {
            return;
        }

        $streak = $user->streak_current;
        if ($last === null) {
            $streak = 1;
        } else {
            $gap = (int) $last->diffInDays($today);
            if ($gap === 1) {
                $streak++;
            } elseif ($gap === 2 && $this->canFreeze($user, $today)) {
                $streak++;
                $user->streak_freeze_used_at = $today->copy()->subDay();
            } else {
                $streak = 1;
            }
        }

        $user->forceFill([
            'streak_current' => $streak,
            'streak_longest' => max($user->streak_longest, $streak),
            'last_active_date' => $today->toDateString(),
            'streak_freeze_used_at' => $user->streak_freeze_used_at,
        ])->save();
    }

    /** Nightly: break streaks that can no longer continue (missed day, no freeze left). */
    public function resetBroken(User $user, ?Carbon $now = null): bool
    {
        if ($user->streak_current === 0 || $user->last_active_date === null) {
            return false;
        }
        $today = $this->localDay($now ?? now(), $user);
        $gap = (int) Carbon::parse($user->last_active_date->toDateString())->diffInDays($today);
        if ($gap <= 1 || ($gap === 2 && $this->canFreeze($user, $today))) {
            return false;
        }
        $user->forceFill(['streak_current' => 0])->save();

        return true;
    }

    /** The learner's calendar day as a timezone-free date (so day differences are whole numbers). */
    private function localDay(Carbon $at, User $user): Carbon
    {
        return Carbon::parse($at->copy()->setTimezone($user->timezone)->toDateString());
    }

    private function canFreeze(User $user, Carbon $today): bool
    {
        $used = $user->streak_freeze_used_at;

        return $used === null || $used->copy()->startOfWeek()->notEqualTo($today->copy()->startOfWeek());
    }
}
