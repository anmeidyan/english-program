<?php

declare(strict_types=1);

namespace App\Services\Grading\Graders;

use App\Models\Question;
use App\Services\Grading\GradeResult;
use App\Services\Grading\QuestionGraderInterface;

class TrueFalseNgGrader implements QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult
    {
        $correct = (string) ($question->answer_key['correct'] ?? '');

        return is_string($answer) && strtolower($answer) === $correct ? GradeResult::correct() : GradeResult::wrong();
    }

    public function correctAnswer(Question $question): mixed
    {
        return $question->answer_key['correct'] ?? null;
    }
}
