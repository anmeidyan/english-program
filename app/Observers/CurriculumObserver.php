<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Lesson;
use App\Models\Level;
use App\Models\Module;
use App\Models\Unit;
use App\Models\UserProgress;
use App\Services\Content\ExamProvisioner;
use App\Services\CurriculumService;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Keeps the cached curriculum tree fresh and every lesson/unit/level backed by an exam.
 * Content that already has learner progress can only be soft-deleted (PRD 9.4).
 */
class CurriculumObserver
{
    public function __construct(
        private readonly CurriculumService $curriculum,
        private readonly ExamProvisioner $exams,
    ) {}

    public function created(Model $model): void
    {
        match (true) {
            $model instanceof Lesson => $this->exams->forLesson($model, $model->slug),
            $model instanceof Unit => $this->exams->forUnit($model, $model->slug),
            $model instanceof Level => $this->exams->forLevel($model, strtolower($model->code->value)),
            default => null,
        };
        $this->curriculum->flush();
    }

    public function saved(Model $model): void
    {
        $this->curriculum->flush();
    }

    public function deleted(Model $model): void
    {
        $this->curriculum->flush();
    }

    public function restored(Model $model): void
    {
        $this->curriculum->flush();
    }

    public function forceDeleting(Model $model): void
    {
        if ($model instanceof Module) {
            return;
        }
        $hasProgress = UserProgress::query()
            ->where('progressable_type', $model->getMorphClass())
            ->where('progressable_id', $model->getKey())
            ->exists();

        if ($hasProgress) {
            throw new RuntimeException('Konten yang sudah memiliki progres pelajar tidak boleh dihapus permanen.');
        }
    }
}
