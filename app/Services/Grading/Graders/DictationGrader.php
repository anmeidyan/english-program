<?php

declare(strict_types=1);

namespace App\Services\Grading\Graders;

use App\Models\Question;
use App\Services\Grading\GradeResult;
use App\Services\Grading\QuestionGraderInterface;
use App\Services\Grading\TextNormalizer;

/**
 * Listen-and-type. Word by word; words longer than 5 letters tolerate one typo (Levenshtein ≤ 1).
 * Correct only when every word matches; partial credit otherwise.
 */
class DictationGrader implements QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult
    {
        $target = TextNormalizer::words((string) ($question->answer_key['text'] ?? ''));
        $given = TextNormalizer::words(is_scalar($answer) ? (string) $answer : '');
        if ($target === []) {
            return GradeResult::wrong();
        }

        $hits = 0;
        foreach ($target as $i => $word) {
            $candidate = $given[$i] ?? '';
            if ($candidate === $word || (mb_strlen($word) > 5 && levenshtein($candidate, $word) <= 1)) {
                $hits++;
            }
        }
        $fraction = $hits / max(count($target), count($given));

        return $fraction >= 1.0 ? GradeResult::correct() : GradeResult::partial($fraction);
    }

    public function correctAnswer(Question $question): mixed
    {
        return $question->answer_key['text'] ?? null;
    }
}
