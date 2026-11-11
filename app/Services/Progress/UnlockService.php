<?php

declare(strict_types=1);

namespace App\Services\Progress;

use App\Enums\ProgressStatus;
use App\Enums\RemedialStatus;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\RemedialTask;
use App\Models\TenseTopic;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\CurriculumService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The mastery gate (PRD 5, 8.3). Single source of truth for what a learner may open,
 * what may be examined, and what unlocks after a pass. Enforced server-side by
 * EnsureContentUnlocked middleware, policies and ExamService.
 */
class UnlockService
{
    public function __construct(
        private readonly ProgressRepositoryInterface $progress,
        private readonly CurriculumService $curriculum,
    ) {}

    // ---------------------------------------------------------------- initialisation

    /** Lazily creates the first rows of the start level (PRD 9.4). Never downgrades existing rows. */
    public function initialize(User $user, ?Level $start = null): void
    {
        $start ??= $user->current_level_id ? Level::find($user->current_level_id) : null;
        $start ??= $this->curriculum->firstPublishedLevel();
        if ($start === null) {
            return;
        }

        DB::transaction(function () use ($user, $start) {
            $this->openIfLocked($user, $start);
            $unit = $this->curriculum->firstUnit($start);
            if ($unit !== null) {
                $this->openIfLocked($user, $unit);
                $lesson = $this->curriculum->firstLesson($unit);
                if ($lesson !== null) {
                    $this->openIfLocked($user, $lesson);
                }
            }
            if ($user->current_level_id !== $start->id) {
                $user->forceFill(['current_level_id' => $start->id])->save();
            }
        });
    }

    public function ensureInitialized(User $user): void
    {
        if ($user->current_level_id === null || ! $user->progress()->exists()) {
            $this->initialize($user);
        }
    }

    // ---------------------------------------------------------------- access

    public function lessonDecision(User $user, Lesson $lesson): AccessDecision
    {
        if (! $lesson->is_published) {
            return AccessDecision::deny(AccessDecision::UNPUBLISHED, __('Materi ini belum tersedia.'), route('dashboard'));
        }

        if ($this->isLessonOpen($user, $lesson)) {
            return AccessDecision::allow();
        }

        return AccessDecision::deny(
            AccessDecision::LOCKED,
            __('Materi ini masih terkunci. Selesaikan materi sebelumnya terlebih dahulu.'),
            $this->nextActionUrl($user),
        );
    }

    public function isLessonOpen(User $user, Lesson $lesson): bool
    {
        if ($this->progress->status($user, $lesson)->isOpen()) {
            return true;
        }

        // passed (or placed-out) levels stay open for review
        return $this->progress->status($user, $this->curriculum->levelOfLesson($lesson))->isPassed();
    }

    public function examDecision(User $user, Exam $exam): AccessDecision
    {
        if (! $exam->is_published) {
            return AccessDecision::deny(AccessDecision::UNPUBLISHED, __('Ujian ini belum tersedia.'), route('dashboard'));
        }

        $item = $exam->examable;
        $row = $this->progress->find($user, $item);
        $status = $row->status ?? ProgressStatus::Locked;

        if ($item instanceof TenseTopic && $status === ProgressStatus::Locked && $this->tenseTopicOpen($user, $item)) {
            $status = ProgressStatus::ExamReady;
        }

        if ($item instanceof Lesson && $status->isPassed() && $this->pendingRemedialFor($user, $item)) {
            return AccessDecision::allow(); // remedial: re-take the quiz
        }

        if ($status === ProgressStatus::ExamInProgress) {
            return AccessDecision::allow(); // resume the active attempt
        }

        if ($status === ProgressStatus::PendingReview) {
            return AccessDecision::deny(AccessDecision::PENDING_REVIEW, __('Ujianmu sedang dinilai. Hasilnya akan dikirim maksimal 48 jam.'), $this->nextActionUrl($user));
        }

        if ($status->isPassed()) {
            return AccessDecision::deny(AccessDecision::ALREADY_PASSED, __('Kamu sudah lulus ujian ini.'), $this->nextActionUrl($user));
        }

        if ($status === ProgressStatus::Failed) {
            if ($row?->cooldown_until !== null && $row->cooldown_until->isFuture()) {
                return AccessDecision::deny(AccessDecision::COOLDOWN, __('Kamu bisa mengulang ujian setelah masa tunggu selesai.'), null, $row->cooldown_until);
            }
            if ($this->pendingRemedialCount($user, $exam) > 0) {
                return AccessDecision::deny(AccessDecision::REMEDIAL, __('Selesaikan dulu lesson "wajib review" sebelum mengulang ujian.'), route('dashboard'));
            }

            return AccessDecision::allow();
        }

        if ($status === ProgressStatus::ExamReady) {
            return AccessDecision::allow();
        }

        if ($status === ProgressStatus::Locked) {
            return AccessDecision::deny(AccessDecision::LOCKED, __('Ujian ini masih terkunci.'), $this->nextActionUrl($user));
        }

        $message = $item instanceof Lesson
            ? __('Pelajari materi lesson ini sampai selesai sebelum mengikuti quiz.')
            : __('Selesaikan semua materi sebelumnya sebelum mengikuti ujian ini.');

        return AccessDecision::deny(AccessDecision::NOT_READY, $message, $this->nextActionUrl($user));
    }

    // ---------------------------------------------------------------- transitions

    public function markLessonOpened(User $user, Lesson $lesson): void
    {
        if ($this->progress->status($user, $lesson) === ProgressStatus::Unlocked) {
            $this->progress->put($user, $lesson, ProgressStatus::InProgress);
        }
        $unit = $lesson->unit;
        if ($this->progress->status($user, $unit) === ProgressStatus::Unlocked) {
            $this->progress->put($user, $unit, ProgressStatus::InProgress);
        }
        $level = $this->curriculum->levelOfLesson($lesson);
        if ($this->progress->status($user, $level) === ProgressStatus::Unlocked) {
            $this->progress->put($user, $level, ProgressStatus::InProgress);
        }
    }

    /** Lesson content finished → quiz becomes available. Returns true the first time. */
    public function markLessonCompleted(User $user, Lesson $lesson): bool
    {
        $status = $this->progress->status($user, $lesson);
        if (in_array($status, [ProgressStatus::Unlocked, ProgressStatus::InProgress], true)) {
            $this->progress->put($user, $lesson, ProgressStatus::ExamReady);

            return true;
        }

        return false;
    }

    /** Called (through the ExamPassed listener) after a pass: opens whatever comes next. */
    public function unlockAfterPass(User $user, Model $item): void
    {
        DB::transaction(function () use ($user, $item) {
            match (true) {
                $item instanceof Lesson => $this->afterLessonPassed($user, $item),
                $item instanceof Unit => $this->afterUnitPassed($user, $item),
                $item instanceof Level => $this->afterLevelPassed($user, $item),
                $item instanceof TenseTopic => $this->afterTensePassed($user, $item),
                default => null,
            };
        });
    }

    private function afterLessonPassed(User $user, Lesson $lesson): void
    {
        $next = $this->curriculum->nextLesson($lesson);
        if ($next !== null) {
            $this->openIfLocked($user, $next);

            return;
        }

        $unit = $lesson->unit;
        $allPassed = $this->curriculum->lessonsOfUnit($unit)
            ->every(fn (Lesson $l) => $this->progress->status($user, $l)->isPassed());
        $unitStatus = $this->progress->status($user, $unit);
        if ($allPassed && ! $unitStatus->isPassed() && $unitStatus !== ProgressStatus::Failed) {
            $this->progress->put($user, $unit, ProgressStatus::ExamReady);
        }
    }

    private function afterUnitPassed(User $user, Unit $unit): void
    {
        $next = $this->curriculum->nextUnit($unit);
        if ($next !== null) {
            $this->openIfLocked($user, $next);
            if ($first = $this->curriculum->firstLesson($next)) {
                $this->openIfLocked($user, $first);
            }

            return;
        }

        $level = $this->curriculum->levelOfUnit($unit);
        $allPassed = $this->curriculum->unitsOf($level)->every(fn (Unit $u) => $this->progress->status($user, $u)->isPassed());
        $levelStatus = $this->progress->status($user, $level);
        if ($allPassed && ! $levelStatus->isPassed() && $levelStatus !== ProgressStatus::Failed) {
            $this->progress->put($user, $level, ProgressStatus::ExamReady);
        }
    }

    private function afterLevelPassed(User $user, Level $level): void
    {
        $next = $this->curriculum->nextLevel($level);
        if ($next === null) {
            return;
        }
        $this->initialize($user, $next);
    }

    private function afterTensePassed(User $user, TenseTopic $topic): void
    {
        $next = TenseTopic::query()->published()->where('sort_order', '>', $topic->sort_order)->orderBy('sort_order')->first();
        if ($next !== null && $this->tenseLevelReached($user, $next)) {
            $this->openIfLocked($user, $next, ProgressStatus::ExamReady);
        }
    }

    // ---------------------------------------------------------------- tenses master

    /** A tense opens when the learner reached its level and passed the previous tense (PRD 3.3). */
    public function tenseTopicOpen(User $user, TenseTopic $topic): bool
    {
        if ($this->progress->status($user, $topic)->isOpen()) {
            return true;
        }
        if (! $this->tenseLevelReached($user, $topic)) {
            return false;
        }
        $previous = TenseTopic::query()->published()->where('sort_order', '<', $topic->sort_order)->orderByDesc('sort_order')->first();

        return $previous === null || $this->progress->status($user, $previous)->isPassed();
    }

    public function tenseLevelReached(User $user, TenseTopic $topic): bool
    {
        if ($topic->required_level_id === null) {
            return true;
        }
        $required = $topic->requiredLevel;
        $current = $user->currentLevel;

        return $required === null || ($current !== null && $current->sort_order >= $required->sort_order);
    }

    // ---------------------------------------------------------------- remedial helpers

    public function pendingRemedialFor(User $user, Lesson $lesson): bool
    {
        return RemedialTask::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)
            ->where('status', RemedialStatus::Pending->value)->exists();
    }

    public function pendingRemedialCount(User $user, Exam $exam): int
    {
        return RemedialTask::query()->where('user_id', $user->id)
            ->where('status', RemedialStatus::Pending->value)
            ->whereHas('attempt', fn ($q) => $q->where('exam_id', $exam->id))
            ->count();
    }

    // ---------------------------------------------------------------- "what next?"

    /**
     * The next thing the learner should do in their current level.
     *
     * @return array{kind: 'lesson'|'quiz'|'unit_exam'|'level_exam'|'done'|'none', model: Model|null, url: string, status: ProgressStatus|null}
     */
    public function nextAction(User $user): array
    {
        $this->ensureInitialized($user);
        $level = $user->current_level_id ? $this->curriculum->tree($user->current_level_id) : null;
        if ($level === null) {
            return ['kind' => 'none', 'model' => null, 'url' => route('dashboard'), 'status' => null];
        }

        $units = $this->curriculum->unitsOf($level);
        $unitRows = $this->progress->forItems($user, 'unit', $units->pluck('id')->all());
        $lessonRows = $this->progress->forItems($user, 'lesson', $units->flatMap(fn (Unit $u) => $u->lessons->pluck('id'))->all());

        foreach ($units as $unit) {
            $unitStatus = $unitRows[$unit->id]->status ?? ProgressStatus::Locked;
            if ($unitStatus->isPassed()) {
                continue;
            }
            foreach ($unit->lessons as $lesson) {
                $status = $lessonRows[$lesson->id]->status ?? ProgressStatus::Locked;
                if ($status->isPassed()) {
                    continue;
                }
                if (in_array($status, [ProgressStatus::ExamReady, ProgressStatus::Failed, ProgressStatus::ExamInProgress], true) && $lesson->exam) {
                    return ['kind' => 'quiz', 'model' => $lesson, 'url' => route('exams.show', $lesson->exam), 'status' => $status];
                }

                return ['kind' => 'lesson', 'model' => $lesson, 'url' => route('lessons.show', $lesson), 'status' => $status];
            }
            if ($unit->exam) {
                return ['kind' => 'unit_exam', 'model' => $unit, 'url' => route('exams.show', $unit->exam), 'status' => $unitStatus];
            }
        }

        $levelStatus = $this->progress->status($user, $level);
        if (! $levelStatus->isPassed() && $level->exam) {
            return ['kind' => 'level_exam', 'model' => $level, 'url' => route('exams.show', $level->exam), 'status' => $levelStatus];
        }

        return ['kind' => 'done', 'model' => $level, 'url' => route('learn.level', $level), 'status' => $levelStatus];
    }

    public function nextActionUrl(User $user): string
    {
        return $this->nextAction($user)['url'];
    }

    private function openIfLocked(User $user, Model $item, ProgressStatus $status = ProgressStatus::Unlocked): void
    {
        if ($this->progress->find($user, $item) === null) {
            $this->progress->put($user, $item, $status);
        }
    }
}
