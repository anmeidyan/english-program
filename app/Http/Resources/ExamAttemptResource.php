<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExamAttempt;
use App\Services\Exam\ExamService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attempt as sent to the exam runner. Questions come from QuestionPresenter,
 * which never includes answer_key or explanations (PRD 5.5, 13.1).
 *
 * @mixin ExamAttempt
 */
class ExamAttemptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'attempt_number' => $this->attempt_number,
            'started_at' => $this->started_at->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'seconds_remaining' => $this->secondsRemaining(),
            'max_audio_plays' => $this->exam->max_audio_plays,
            'questions' => app(ExamService::class)->presentQuestions($this->resource),
        ];
    }
}
