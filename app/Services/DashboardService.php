<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\ProgressStatus;
use App\Enums\Skill;
use App\Models\ExamAttempt;
use App\Models\Level;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserFlashcard;
use App\Models\UserProgress;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\Exam\RemedialService;
use App\Services\Progress\UnlockService;

/** PRD 6.1: where am I, what next, which skill is weak. */
class DashboardService
{
    public function __construct(
        private readonly UnlockService $unlock,
        private readonly CurriculumService $curriculum,
        private readonly ProgressRepositoryInterface $progress,
        private readonly RemedialService $remedial,
    ) {}

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        $next = $this->unlock->nextAction($user);
        $level = $user->current_level_id ? $this->curriculum->tree($user->current_level_id) : null;

        return [
            'next' => $next,
            'level' => $level,
            'levelProgress' => $level ? $this->levelProgress($user, $level) : null,
            'radar' => $this->radar($user),
            'remedials' => $this->remedial->pending($user),
            'history' => ExamAttempt::query()->with('exam')->where('user_id', $user->id)
                ->where('status', '!=', AttemptStatus::InProgress->value)->latest('submitted_at')->limit(10)->get(),
            'dueCards' => UserFlashcard::query()->where('user_id', $user->id)->where('due_at', '<=', now())->count(),
            'badges' => $user->badges()->with('badge')->latest('awarded_at')->limit(6)->get(),
        ];
    }

    /** @return array{percent: int, passed: int, total: int, hours_left: float} */
    public function levelProgress(User $user, Level $level): array
    {
        $units = $this->curriculum->unitsOf($level);
        $lessonIds = $units->flatMap(fn (Unit $u) => $u->lessons->pluck('id'))->all();
        $unitIds = $units->pluck('id')->all();
        $total = count($lessonIds) + count($unitIds) + 1;

        $passedStatuses = [ProgressStatus::Passed->value, ProgressStatus::PassedByPlacement->value];
        $passed = UserProgress::query()->where('user_id', $user->id)->whereIn('status', $passedStatuses)
            ->where(function ($q) use ($lessonIds, $unitIds, $level) {
                $q->where(fn ($w) => $w->where('progressable_type', 'lesson')->whereIn('progressable_id', $lessonIds ?: [0]))
                    ->orWhere(fn ($w) => $w->where('progressable_type', 'unit')->whereIn('progressable_id', $unitIds ?: [0]))
                    ->orWhere(fn ($w) => $w->where('progressable_type', 'level')->where('progressable_id', $level->id));
            })->count();

        $percent = (int) floor($passed / max(1, $total) * 100);

        return [
            'percent' => $percent,
            'passed' => $passed,
            'total' => $total,
            'hours_left' => round($level->estimated_hours * (1 - $percent / 100), 1),
        ];
    }

    /**
     * Average per-skill score of the last 5 graded exams (6 axes).
     *
     * @return array<string, float|null>
     */
    public function radar(User $user): array
    {
        $attempts = ExamAttempt::query()->where('user_id', $user->id)
            ->whereIn('status', [AttemptStatus::Passed->value, AttemptStatus::Failed->value])
            ->latest('submitted_at')->limit(5)->pluck('skill_scores');

        $out = [];
        foreach (Skill::cases() as $skill) {
            $values = $attempts->map(fn ($s) => $s[$skill->value] ?? null)->filter(fn ($v) => $v !== null);
            $out[$skill->value] = $values->isEmpty() ? null : round((float) $values->avg(), 1);
        }

        return $out;
    }

    /**
     * Learning path map for one level: each unit with its lessons, statuses and exam state.
     *
     * @return array<string, mixed>
     */
    public function path(User $user, Level $level): array
    {
        $this->unlock->ensureInitialized($user);
        $tree = $this->curriculum->tree($level) ?? $level;
        $units = $this->curriculum->unitsOf($tree);
        $lessonRows = $this->progress->forItems($user, 'lesson', $units->flatMap(fn (Unit $u) => $u->lessons->pluck('id'))->all());
        $unitRows = $this->progress->forItems($user, 'unit', $units->pluck('id')->all());
        $levelRow = $this->progress->find($user, $level);
        $levelOpen = $levelRow?->status->isPassed() ?? false;

        return [
            'level' => $tree,
            'levelRow' => $levelRow,
            'levelOpen' => $levelOpen,
            'lessonRows' => $lessonRows,
            'unitRows' => $unitRows,
        ];
    }
}
