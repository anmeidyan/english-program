<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\RemedialStatus;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\RemedialTask;
use App\Models\Unit;
use App\Models\User;
use App\Services\CurriculumService;
use Illuminate\Support\Collection;

/**
 * PRD 5.3 remedial plan: after a failed unit/level exam, lessons of the weak skills are marked
 * "wajib review" and their quiz must be passed again (≥ 80%) before a retake.
 */
class RemedialService
{
    public function __construct(
        private readonly CurriculumService $curriculum,
        private readonly ScoringService $scoring,
    ) {}

    /** @return Collection<int, RemedialTask> */
    public function createFor(ExamAttempt $attempt): Collection
    {
        $exam = $attempt->exam;
        $item = $exam->examable;
        $lessons = match (true) {
            $item instanceof Unit => $this->curriculum->lessonsOfUnit($item),
            $item instanceof Level => $this->curriculum->lessonsOfLevel($item),
            default => collect(),
        };
        if ($lessons->isEmpty()) {
            return collect();
        }

        $weak = $this->scoring->weakSkills($exam, $attempt->skill_scores ?? []);
        $targets = $lessons->filter(fn (Lesson $l) => in_array($l->skill->value, $weak, true));

        $tasks = collect();
        foreach ($targets as $lesson) {
            $tasks->push(RemedialTask::query()->firstOrCreate(
                ['user_id' => $attempt->user_id, 'lesson_id' => $lesson->id, 'status' => RemedialStatus::Pending->value],
                ['exam_attempt_id' => $attempt->id],
            ));
        }

        return $tasks;
    }

    public function completeFor(User $user, Lesson $lesson): int
    {
        return RemedialTask::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('status', RemedialStatus::Pending->value)
            ->update(['status' => RemedialStatus::Completed->value, 'completed_at' => now()]);
    }

    /** @return Collection<int, RemedialTask> */
    public function pending(User $user): Collection
    {
        return RemedialTask::query()->with(['lesson', 'attempt.exam'])
            ->where('user_id', $user->id)
            ->where('status', RemedialStatus::Pending->value)
            ->get();
    }
}
