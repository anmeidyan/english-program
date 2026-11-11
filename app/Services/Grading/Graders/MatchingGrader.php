<?php

declare(strict_types=1);

namespace App\Services\Grading\Graders;

use App\Models\Question;
use App\Services\Grading\GradeResult;
use App\Services\Grading\QuestionGraderInterface;

/** Answer = {leftKey: rightKey}. Partial credit per correct pair. */
class MatchingGrader implements QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult
    {
        /** @var array<string, string> $pairs */
        $pairs = $question->answer_key['pairs'] ?? [];
        if ($pairs === [] || ! is_array($answer)) {
            return GradeResult::wrong();
        }
        $hits = 0;
        foreach ($pairs as $left => $right) {
            if (isset($answer[$left]) && (string) $answer[$left] === (string) $right) {
                $hits++;
            }
        }

        return GradeResult::partial($hits / count($pairs));
    }

    public function correctAnswer(Question $question): mixed
    {
        return $question->answer_key['pairs'] ?? [];
    }
}
