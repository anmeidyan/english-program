<?php

declare(strict_types=1);

namespace App\Services\Grading;

use App\Models\Question;

interface QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult;

    /**
     * The correct answer in a learner-facing form, revealed only after submission
     * (practice, lesson quiz, tenses checkpoint).
     */
    public function correctAnswer(Question $question): mixed;
}
