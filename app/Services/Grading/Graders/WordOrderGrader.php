<?php

declare(strict_types=1);

namespace App\Services\Grading\Graders;

use App\Models\Question;
use App\Services\Grading\GradeResult;
use App\Services\Grading\QuestionGraderInterface;
use App\Services\Grading\TextNormalizer;

/** Answer = ordered token list (or a string); compared with accepted sentences ignoring case/punctuation. */
class WordOrderGrader implements QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult
    {
        $sentence = is_array($answer) ? implode(' ', array_filter($answer, 'is_scalar')) : (string) (is_scalar($answer) ? $answer : '');

        return TextNormalizer::matchesAny($sentence, $question->answer_key['accepted'] ?? [])
            ? GradeResult::correct()
            : GradeResult::wrong();
    }

    public function correctAnswer(Question $question): mixed
    {
        return $question->answer_key['accepted'][0] ?? null;
    }
}
