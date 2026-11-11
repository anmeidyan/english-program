<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\User;
use App\Models\XpLog;
use Illuminate\Support\Facades\DB;

/** PRD 6.2 XP. Each (source_type, source_id) pays out once per learner. */
class XpService
{
    public function award(User $user, string $sourceType, ?int $sourceId, int $amount): bool
    {
        if ($amount === 0) {
            return false;
        }

        return DB::transaction(function () use ($user, $sourceType, $sourceId, $amount) {
            $exists = XpLog::query()->where('user_id', $user->id)
                ->where('source_type', $sourceType)->where('source_id', $sourceId)
                ->lockForUpdate()->exists();
            if ($exists) {
                return false;
            }
            XpLog::query()->create(['user_id' => $user->id, 'amount' => $amount, 'source_type' => $sourceType, 'source_id' => $sourceId]);
            User::query()->whereKey($user->id)->increment('xp_total', $amount);
            $user->xp_total += $amount;

            return true;
        });
    }

    public function awardConfigured(User $user, string $key, ?int $sourceId): bool
    {
        return $this->award($user, $key, $sourceId, (int) config("englishpath.xp.{$key}", 0));
    }

    /** Daily flashcard bonus: source id = yyyymmdd in the learner's timezone. */
    public function awardDailyFlashcards(User $user): bool
    {
        $day = (int) now($user->timezone)->format('Ymd');

        return $this->awardConfigured($user, 'flashcards_daily', $day);
    }
}
