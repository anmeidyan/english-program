<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FlashcardRating;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserFlashcard;
use App\Services\Gamification\StreakService;
use App\Services\Gamification\XpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** PRD 4.2 / 6.3: personal deck with SM-2 spaced repetition. */
class FlashcardService
{
    public function __construct(
        private readonly XpService $xp,
        private readonly StreakService $streak,
    ) {}

    /** Words of a finished lesson join the learner's deck (due now). */
    public function addLessonWords(User $user, Lesson $lesson): int
    {
        $added = 0;
        foreach ($lesson->vocabularies()->pluck('vocabularies.id') as $vocabId) {
            $card = UserFlashcard::query()->firstOrCreate(
                ['user_id' => $user->id, 'vocabulary_id' => $vocabId],
                ['ease_factor' => 2.5, 'interval_days' => 0, 'repetitions' => 0, 'due_at' => now()],
            );
            $added += $card->wasRecentlyCreated ? 1 : 0;
        }

        return $added;
    }

    public function addWord(User $user, int $vocabularyId): UserFlashcard
    {
        return UserFlashcard::query()->firstOrCreate(
            ['user_id' => $user->id, 'vocabulary_id' => $vocabularyId],
            ['ease_factor' => 2.5, 'interval_days' => 0, 'repetitions' => 0, 'due_at' => now()],
        );
    }

    /**
     * Today's session: due cards (max 50) + new cards (max 10).
     *
     * @return Collection<int, UserFlashcard>
     */
    public function session(User $user): Collection
    {
        $maxDue = (int) config('englishpath.flashcards.max_due', 50);
        $maxNew = (int) config('englishpath.flashcards.max_new', 10);

        $due = UserFlashcard::query()->with('vocabulary')->where('user_id', $user->id)
            ->whereNotNull('last_reviewed_at')->where('due_at', '<=', now())
            ->orderBy('due_at')->limit($maxDue)->get();
        $new = UserFlashcard::query()->with('vocabulary')->where('user_id', $user->id)
            ->whereNull('last_reviewed_at')->orderBy('id')->limit($maxNew)->get();

        return $due->concat($new)->values();
    }

    /** SM-2 update. Again → card returns in 10 minutes. */
    public function review(UserFlashcard $card, FlashcardRating $rating, ?Carbon $now = null): UserFlashcard
    {
        $now ??= now();
        $q = $rating->quality();
        $ef = $card->ease_factor;

        if ($q < 3) {
            $repetitions = 0;
            $interval = 0;
            $due = $now->copy()->addMinutes(10);
        } else {
            $repetitions = $card->repetitions + 1;
            $interval = match ($repetitions) {
                1 => 1,
                2 => 6,
                default => (int) round(max(1, $card->interval_days) * $ef),
            };
            if ($rating === FlashcardRating::Easy) {
                $interval = (int) ceil($interval * 1.3);
            }
            $due = $now->copy()->addDays($interval);
        }

        $ef = max(1.3, round($ef + (0.1 - (5 - $q) * (0.08 + (5 - $q) * 0.02)), 2));

        $card->update([
            'ease_factor' => $ef,
            'interval_days' => $interval,
            'repetitions' => $repetitions,
            'due_at' => $due,
            'last_reviewed_at' => $now,
        ]);

        $this->trackDaily($card->user()->firstOrFail());

        return $card;
    }

    /** 20 reviews in a day keeps the streak and pays the daily XP (PRD 6.2). */
    private function trackDaily(User $user): void
    {
        $key = 'flashcards:'.$user->id.':'.now($user->timezone)->format('Ymd');
        $count = Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, 1, now()->addDays(2));
        }
        if ((int) $count === (int) config('englishpath.streak.flashcards_per_day', 20)) {
            $this->streak->recordActivity($user);
            $this->xp->awardDailyFlashcards($user);
        }
    }
}
