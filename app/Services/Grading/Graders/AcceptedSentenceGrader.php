<?php

declare(strict_types=1);

namespace App\Services\Grading\Graders;

use App\Models\Question;
use App\Services\Grading\GradeResult;
use App\Services\Grading\QuestionGraderInterface;
use App\Services\Grading\TextNormalizer;

/** error_correction and transformation: the answer must match one accepted sentence. */
class AcceptedSentenceGrader implements QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult
    {
        return TextNormalizer::matchesAny(is_scalar($answer) ? (string) $answer : '', $question->answer_key['accepted'] ?? [])
            ? GradeResult::correct()
            : GradeResult::wrong();
    }

    public function correctAnswer(Question $question): mixed
    {
        return $question->answer_key['accepted'][0] ?? null;
    }
}
