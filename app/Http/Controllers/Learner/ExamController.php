<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExamAttemptResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\Exam\ExamService;
use App\Services\Exam\RemedialService;
use App\Services\Grading\GraderRegistry;
use App\Services\Progress\UnlockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function __construct(
        private readonly UnlockService $unlock,
        private readonly ExamService $exams,
        private readonly ProgressRepositoryInterface $progress,
        private readonly RemedialService $remedial,
        private readonly GraderRegistry $graders,
    ) {}

    /** Intro: rules, cooldown countdown, remedial tasks (PRD 10.1). */
    public function show(Request $request, Exam $exam): View
    {
        $user = $request->user();
        $exam->load('examable', 'rules');

        return view('learner.exam-intro', [
            'exam' => $exam,
            'decision' => $this->unlock->examDecision($user, $exam),
            'row' => $this->progress->find($user, $exam->examable),
            'active' => $this->exams->activeAttempt($user, $exam),
            'remedials' => $this->remedial->pending($user)->filter(fn ($t) => $t->attempt->exam_id === $exam->id),
            'history' => ExamAttempt::query()->where('user_id', $user->id)->where('exam_id', $exam->id)->latest()->limit(5)->get(),
        ]);
    }

    public function run(ExamAttempt $attempt): View|RedirectResponse
    {
        if (! $attempt->isActive()) {
            return redirect()->route('exams.result', $attempt);
        }
        if ($attempt->isPastGrace()) {
            $this->exams->finalize($attempt);

            return redirect()->route('exams.result', $attempt);
        }
        $attempt->load('exam.examable');

        return view('learner.exam-runner', [
            'attempt' => $attempt,
            'payload' => (new ExamAttemptResource($attempt))->resolve(),
        ]);
    }

    public function result(ExamAttempt $attempt): View|RedirectResponse
    {
        if ($attempt->status === AttemptStatus::InProgress) {
            if (! $attempt->isPastGrace()) {
                return redirect()->route('exams.run', $attempt);
            }
            $attempt = $this->exams->finalize($attempt);
        }
        $attempt->load('exam.examable', 'answers.question', 'answers.submission');
        $reveal = $attempt->exam->type->revealsAnswers();

        $review = [];
        if ($reveal) {
            foreach ($attempt->answers as $answer) {
                /** @var Question $q */
                $q = $answer->question;
                if ($answer->is_correct === false || (float) $answer->points_awarded < $q->points) {
                    $review[] = [
                        'prompt' => $q->prompt,
                        'type' => $q->type->value,
                        'your_answer' => $answer->answer,
                        'correct' => $this->graders->correctAnswer($q),
                        'explanation' => $q->explanation,
                    ];
                }
            }
        }

        return view('learner.exam-result', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'reveal' => $reveal,
            'review' => $review,
            'row' => $this->progress->find($attempt->user, $attempt->exam->examable),
            'remedials' => $this->remedial->pending($attempt->user)->filter(fn ($t) => $t->exam_attempt_id === $attempt->id),
            'nextUrl' => $this->unlock->nextActionUrl($attempt->user),
        ]);
    }
}
