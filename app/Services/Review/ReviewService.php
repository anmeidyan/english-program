<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Enums\AttemptStatus;
use App\Enums\GradedBy;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\ReviewResultAvailable;
use App\Services\AuditLogger;
use App\Services\Exam\ExamService;
use Illuminate\Support\Facades\DB;

/** Stage-2 scoring of speaking/writing by a reviewer (PRD 4.5, 4.6, 5.2). */
class ReviewService
{
    public function __construct(
        private readonly ExamService $exams,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, int|float>  $scores  criterion => band 0–9
     * @param  list<array{quote:string, comment:string}>  $inline
     */
    public function grade(Submission $submission, User $reviewer, array $scores, ?string $feedback, array $inline = []): Submission
    {
        $wasReviewed = $submission->status === SubmissionStatus::Reviewed;

        DB::transaction(function () use ($submission, $reviewer, $scores, $feedback, $inline) {
            $fraction = Rubric::fraction($submission->type, $scores);
            $answer = $submission->attemptAnswer()->with('question')->firstOrFail();

            $submission->update([
                'final_score' => $scores,
                'feedback' => $feedback,
                'inline_feedback' => $inline,
                'reviewer_id' => $reviewer->id,
                'status' => SubmissionStatus::Reviewed,
                'reviewed_at' => now(),
            ]);

            $answer->update([
                'is_correct' => $fraction >= 0.5,
                'points_awarded' => round($fraction * max(1, $answer->question->points), 2),
                'graded_by' => GradedBy::Reviewer,
            ]);
        });

        $this->audit->log($reviewer, $wasReviewed ? 'submission.regraded' : 'submission.graded', $submission, ['scores' => $scores]);

        $attempt = $submission->attemptAnswer->attempt;
        $final = $this->exams->rescoreAfterReview($attempt);
        if ($final->status->isFinal() && $attempt->status === AttemptStatus::PendingReview) {
            $final->user->notify(new ReviewResultAvailable($final));
        }

        return $submission->refresh();
    }
}
