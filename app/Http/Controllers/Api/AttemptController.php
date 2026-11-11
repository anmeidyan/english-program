<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogEventRequest;
use App\Http\Requests\SaveAnswerRequest;
use App\Http\Requests\UploadRecordingRequest;
use App\Http\Resources\ExamAttemptResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\Exam\ExamService;
use App\Services\RecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON endpoints of the exam runner (PRD 10.2). Grading happens only on the server. */
class AttemptController extends Controller
{
    public function __construct(
        private readonly ExamService $exams,
        private readonly RecordingService $recordings,
    ) {}

    public function start(Request $request, Exam $exam): JsonResponse
    {
        $attempt = $this->exams->start($request->user(), $exam);

        return response()->json([
            'attempt' => new ExamAttemptResource($attempt->load('exam')),
            'runner_url' => route('exams.run', $attempt),
        ], 201);
    }

    public function show(ExamAttempt $attempt): ExamAttemptResource
    {
        return new ExamAttemptResource($attempt->load('exam'));
    }

    public function saveAnswer(SaveAnswerRequest $request, ExamAttempt $attempt, Question $question): JsonResponse
    {
        $this->exams->saveAnswer($attempt, $question, $request->input('answer'));

        return response()->json(['saved' => true, 'seconds_remaining' => $attempt->secondsRemaining()]);
    }

    public function audioPlay(ExamAttempt $attempt, Question $question): JsonResponse
    {
        return response()->json($this->exams->recordAudioPlay($attempt->load('exam'), $question));
    }

    public function uploadRecording(UploadRecordingRequest $request, ExamAttempt $attempt, Question $question): JsonResponse
    {
        $this->recordings->storeForAttempt($attempt, $question, $request->file('audio'), $request->validated('transcript'));

        return response()->json(['saved' => true], 201);
    }

    public function logEvent(LogEventRequest $request, ExamAttempt $attempt): JsonResponse
    {
        $this->exams->logEvent($attempt, $request->validated('type'), $request->validated('meta') ?? []);

        return response()->json(['logged' => true]);
    }

    public function submit(ExamAttempt $attempt): JsonResponse
    {
        $attempt = $this->exams->submit($attempt);

        return response()->json([
            'status' => $attempt->status->value,
            'score' => $attempt->score,
            'passed' => $attempt->passed,
            'result_url' => route('exams.result', $attempt),
        ]);
    }
}
