<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\CurriculumService;
use App\Services\Grading\QuestionPresenter;
use App\Services\LessonService;
use App\Services\Progress\UnlockService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function __construct(
        private readonly LessonService $lessons,
        private readonly QuestionPresenter $presenter,
        private readonly ProgressRepositoryInterface $progress,
        private readonly CurriculumService $curriculum,
        private readonly UnlockService $unlock,
    ) {}

    public function show(Request $request, Lesson $lesson): View
    {
        $user = $request->user();
        $preview = $user->isStaff() && $request->boolean('preview');
        if (! $preview) {
            $this->lessons->open($user, $lesson);
        }
        $lesson->load(['questions', 'vocabularies', 'exam', 'unit.module.level']);

        return view('learner.lesson', [
            'lesson' => $lesson,
            'preview' => $preview,
            'status' => $this->progress->status($user, $lesson),
            'row' => $this->progress->find($user, $lesson),
            'questions' => $lesson->questions->map(fn ($q) => $this->presenter->present($q, QuestionPresenter::MODE_PRACTICE))->values(),
            'siblings' => $this->curriculum->lessonsOfUnit($lesson->unit),
            'remedial' => $this->unlock->pendingRemedialFor($user, $lesson),
        ]);
    }
}
