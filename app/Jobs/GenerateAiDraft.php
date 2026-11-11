<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Services\Review\AiGraderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Optional AI grader (PRD 4.5/4.6): writes a draft rubric score + feedback the reviewer can correct. */
class GenerateAiDraft implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $submissionId) {}

    public function handle(AiGraderInterface $grader): void
    {
        $submission = Submission::query()->with('attemptAnswer.question')->find($this->submissionId);
        if ($submission === null || $submission->status === SubmissionStatus::Reviewed) {
            return;
        }

        $draft = $grader->draft($submission);
        if ($draft === null) {
            return;
        }

        $submission->update(['ai_score' => $draft, 'status' => SubmissionStatus::AiDraft]);
    }
}
