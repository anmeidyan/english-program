<?php

declare(strict_types=1);

namespace App\Services\Grading\Graders;

use App\Models\Question;
use App\Services\Grading\GradeResult;
use App\Services\Grading\QuestionGraderInterface;
use App\Services\Grading\TextNormalizer;

/**
 * Stage 1 (automatic): transcript vs target text → word accuracy.
 * Exams always go to stage 2 (reviewer) — see ExamGradingService.
 * Answer = {path?: string, transcript?: string}.
 */
class SpeakingGrader implements QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult
    {
        $transcript = is_array($answer) ? (string) ($answer['transcript'] ?? '') : '';
        $target = (string) ($question->payload['target_text'] ?? '');

        if ($target === '') {
            return GradeResult::pending(['transcript' => $transcript]);
        }

        $accuracy = TextNormalizer::wordAccuracy($target, $transcript);

        return new GradeResult(null, $accuracy, ['accuracy' => $accuracy, 'transcript' => $transcript]);
    }

    public function correctAnswer(Question $question): mixed
    {
        return $question->payload['target_text'] ?? null;
    }
}
