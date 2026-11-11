<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LevelCode;
use App\Enums\ProgressStatus;
use App\Enums\QuestionType;
use App\Models\Level;
use App\Models\PlacementResult;
use App\Models\Question;
use App\Models\User;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\Grading\GraderRegistry;
use App\Services\Progress\UnlockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PRD 3.4 adaptive placement: stages of 5 questions per CEFR level, starting at A1.
 * Pass a stage (≥ 4/5) → go up; fail → stop (or step down from A1 to Pre-A1).
 * The first failed level is where the learner starts.
 */
class PlacementService
{
    public const PER_STAGE = 5;

    public const PASS_MARK = 4;

    public const START = 'A1';

    public function __construct(
        private readonly GraderRegistry $graders,
        private readonly UnlockService $unlock,
        private readonly ProgressRepositoryInterface $progress,
        private readonly SettingsService $settings,
    ) {}

    public function nextAllowedAt(User $user): ?Carbon
    {
        $last = PlacementResult::query()->where('user_id', $user->id)->latest('taken_at')->first();
        if ($last === null) {
            return null;
        }
        $next = $last->taken_at->copy()->addDays((int) $this->settings->get('placement.retake_days', 30));

        return $next->isFuture() ? $next : null;
    }

    /** @return list<string> level codes in order */
    public function ladder(): array
    {
        return array_map(fn (LevelCode $c) => $c->value, LevelCode::cases());
    }

    /**
     * @param  list<int>  $exclude
     * @return list<int> question ids for a stage, never repeating ids already asked
     */
    public function stageQuestions(string $levelCode, array $exclude = []): array
    {
        $level = Level::query()->where('code', $levelCode)->first();
        if ($level === null) {
            return [];
        }

        return Question::query()->active()
            ->where('level_id', $level->id)
            ->whereNotIn('type', QuestionType::manualValues())
            ->whereNotIn('id', $exclude ?: [0])
            ->whereHas('tags', fn ($q) => $q->where('name', 'placement'))
            ->inRandomOrder()->limit(self::PER_STAGE)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function isCorrect(Question $question, mixed $answer): bool
    {
        return (bool) $this->graders->grade($question, $answer)->isCorrect;
    }

    /**
     * Decides the next stage after one finished.
     *
     * @param  array<string, int>  $stageScores  level code => correct answers
     * @return array{done: bool, next: string|null, recommended: string|null}
     */
    public function advance(string $current, int $correct, array $stageScores): array
    {
        $ladder = $this->ladder();
        $index = array_search($current, $ladder, true);
        $passed = $correct >= self::PASS_MARK;

        if ($passed) {
            $next = $ladder[$index + 1] ?? null;
            if ($next === null || isset($stageScores[$next])) {
                return ['done' => true, 'next' => null, 'recommended' => $next ?? $current];
            }

            return ['done' => false, 'next' => $next, 'recommended' => null];
        }

        // failed: start here — unless we began at A1 and Pre-A1 is still untested
        if ($current === self::START && ! isset($stageScores[$ladder[0]])) {
            return ['done' => false, 'next' => $ladder[0], 'recommended' => null];
        }

        return ['done' => true, 'next' => null, 'recommended' => $current];
    }

    /**
     * Saves the result and applies it: lower levels → passed_by_placement, start level opened.
     *
     * @param  array<string, int>  $stageScores
     */
    public function finish(User $user, string $recommendedCode, array $stageScores): PlacementResult
    {
        $level = $this->resolvePublished($recommendedCode);

        return DB::transaction(function () use ($user, $level, $stageScores, $recommendedCode) {
            $result = PlacementResult::query()->create([
                'user_id' => $user->id,
                'recommended_level_id' => $level->id,
                'score_detail' => ['stages' => $stageScores, 'raw_recommendation' => $recommendedCode, 'per_stage' => self::PER_STAGE],
                'taken_at' => now(),
            ]);

            $lower = Level::query()->where('sort_order', '<', $level->sort_order)->get();
            foreach ($lower as $below) {
                if (! $this->progress->status($user, $below)->isPassed()) {
                    $this->progress->put($user, $below, ProgressStatus::PassedByPlacement, ['completed_at' => now()]);
                }
            }
            $this->unlock->initialize($user, $level);

            return $result;
        });
    }

    /** Recommendation clamped to what is published (content may not exist yet for higher levels). */
    public function resolvePublished(string $code): Level
    {
        $target = Level::query()->where('code', $code)->first();
        $published = Level::query()->published()->orderBy('sort_order')->get();
        if ($published->isEmpty()) {
            return $target ?? Level::query()->orderBy('sort_order')->firstOrFail();
        }
        if ($target === null) {
            return $published->first();
        }

        return $published->filter(fn (Level $l) => $l->sort_order <= $target->sort_order)->last() ?? $published->first();
    }
}
