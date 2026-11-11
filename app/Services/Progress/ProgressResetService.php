<?php

declare(strict_types=1);

namespace App\Services\Progress;

use App\Models\Level;
use App\Models\RemedialTask;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserProgress;
use App\Services\AuditLogger;
use App\Services\CurriculumService;
use Illuminate\Support\Facades\DB;

/** PRD 7.1: super admin resets a learner's progress for one level, with a reason in the audit log. */
class ProgressResetService
{
    public function __construct(
        private readonly CurriculumService $curriculum,
        private readonly UnlockService $unlock,
        private readonly AuditLogger $audit,
    ) {}

    public function resetLevel(User $actor, User $learner, Level $level, string $reason): int
    {
        return DB::transaction(function () use ($actor, $learner, $level, $reason) {
            $units = $this->curriculum->unitsOf($level);
            $unitIds = $units->pluck('id')->all();
            $lessonIds = $units->flatMap(fn (Unit $u) => $u->lessons->pluck('id'))->all();

            $deleted = UserProgress::query()->where('user_id', $learner->id)
                ->where(function ($q) use ($level, $unitIds, $lessonIds) {
                    $q->where(fn ($w) => $w->where('progressable_type', 'level')->where('progressable_id', $level->id))
                        ->orWhere(fn ($w) => $w->where('progressable_type', 'unit')->whereIn('progressable_id', $unitIds ?: [0]))
                        ->orWhere(fn ($w) => $w->where('progressable_type', 'lesson')->whereIn('progressable_id', $lessonIds ?: [0]));
                })->delete();
            RemedialTask::query()->where('user_id', $learner->id)->whereIn('lesson_id', $lessonIds ?: [0])->delete();

            if ($learner->current_level_id === $level->id) {
                $this->unlock->initialize($learner, $level);
            }

            $this->audit->log($actor, 'progress.reset', $learner, ['level' => $level->code->value, 'reason' => $reason, 'rows' => $deleted]);

            return $deleted;
        });
    }
}
