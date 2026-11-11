<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Enums\ProgressStatus;
use App\Models\Badge;
use App\Models\Level;
use App\Models\TenseTopic;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserFlashcard;
use App\Models\UserProgress;
use Illuminate\Support\Collection;

/**
 * Badge criteria (JSON): lessons_passed {value}, streak {value}, level_passed {value: code},
 * words {value}, tenses_master {}.
 */
class BadgeService
{
    /** @return Collection<int, Badge> newly awarded */
    public function evaluate(User $user): Collection
    {
        $owned = UserBadge::query()->where('user_id', $user->id)->pluck('badge_id')->all();
        $awarded = collect();

        foreach (Badge::query()->whereNotIn('id', $owned)->get() as $badge) {
            if ($this->meets($user, $badge->criteria)) {
                UserBadge::query()->firstOrCreate(['user_id' => $user->id, 'badge_id' => $badge->id], ['awarded_at' => now()]);
                $awarded->push($badge);
            }
        }

        return $awarded;
    }

    /** @param array<string, mixed> $criteria */
    private function meets(User $user, array $criteria): bool
    {
        $value = $criteria['value'] ?? null;

        return match ($criteria['type'] ?? null) {
            'lessons_passed' => $this->passedCount($user, 'lesson') >= (int) $value,
            'streak' => $user->streak_longest >= (int) $value,
            'words' => UserFlashcard::query()->where('user_id', $user->id)->where('repetitions', '>', 0)->count() >= (int) $value,
            'level_passed' => ($level = Level::query()->where('code', $value)->first()) !== null
                && UserProgress::query()->where('user_id', $user->id)->where('progressable_type', 'level')
                    ->where('progressable_id', $level->id)->where('status', ProgressStatus::Passed->value)->exists(),
            'tenses_master' => ($total = TenseTopic::query()->published()->count()) > 0 && $this->passedCount($user, 'tense_topic') >= $total,
            default => false,
        };
    }

    private function passedCount(User $user, string $type): int
    {
        return UserProgress::query()->where('user_id', $user->id)->where('progressable_type', $type)
            ->whereIn('status', [ProgressStatus::Passed->value, ProgressStatus::PassedByPlacement->value])->count();
    }
}
