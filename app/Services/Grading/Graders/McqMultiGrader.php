<?php

declare(strict_types=1);

namespace App\Services\Grading\Graders;

use App\Models\Question;
use App\Services\Grading\GradeResult;
use App\Services\Grading\QuestionGraderInterface;

/** All-or-nothing by default; with answer_key.partial, (hits − false picks) / correct count. */
class McqMultiGrader implements QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult
    {
        $correct = array_map('strval', (array) ($question->answer_key['correct'] ?? []));
        $given = is_array($answer) ? array_values(array_unique(array_map('strval', array_filter($answer, 'is_scalar')))) : [];

        if ($correct === []) {
            return GradeResult::wrong();
        }

        sort($correct);
        sort($given);
        if ($correct === $given) {
            return GradeResult::correct();
        }

        if (! empty($question->answer_key['partial'])) {
            $hits = count(array_intersect($given, $correct));
            $falsePicks = count(array_diff($given, $correct));

            return GradeResult::partial(max(0, $hits - $falsePicks) / count($correct));
        }

        return GradeResult::wrong();
    }

    public function correctAnswer(Question $question): mixed
    {
        return $question->answer_key['correct'] ?? [];
    }
}
