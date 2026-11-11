<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PracticeRequest;
use App\Models\Lesson;
use App\Models\Question;
use App\Services\LessonService;
use App\Services\PracticeService;
use App\Services\Progress\UnlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function __construct(
        private readonly LessonService $lessons,
        private readonly PracticeService $practice,
        private readonly UnlockService $unlock,
    ) {}

    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        $this->lessons->complete($request->user(), $lesson);
        $lesson->loadMissing('exam');

        return response()->json([
            'status' => 'exam_ready',
            'quiz_url' => $lesson->exam ? route('exams.show', $lesson->exam) : null,
            'next_url' => $this->unlock->nextActionUrl($request->user()),
            'xp_total' => $request->user()->fresh()?->xp_total,
        ]);
    }

    public function practice(PracticeRequest $request, Lesson $lesson): JsonResponse
    {
        if ($request->has('items')) {
            /** @var list<array{question_id:int, answer:mixed}> $items */
            $items = $request->validated('items');

            return response()->json($this->practice->checkBatch($request->user(), $lesson, $items));
        }

        $question = Question::query()->findOrFail((int) $request->validated('question_id'));

        return response()->json($this->practice->check($lesson, $question, $request->input('answer')));
    }
}
