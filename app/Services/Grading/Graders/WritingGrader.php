<?php

declare(strict_types=1);

namespace App\Services\Grading\Graders;

use App\Models\Question;
use App\Services\Grading\GradeResult;
use App\Services\Grading\QuestionGraderInterface;

/** Essays are always scored by a reviewer (optionally with an AI draft). */
class WritingGrader implements QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult
    {
        $text = is_array($answer) ? (string) ($answer['text'] ?? '') : (string) (is_scalar($answer) ? $answer : '');
        $words = str_word_count($text);

        return GradeResult::pending(['word_count' => $words]);
    }

    public function correctAnswer(Question $question): mixed
    {
        return null;
    }
}
