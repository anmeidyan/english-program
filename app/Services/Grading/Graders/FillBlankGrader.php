<?php

declare(strict_types=1);

namespace App\Services\Grading\Graders;

use App\Models\Question;
use App\Services\Grading\GradeResult;
use App\Services\Grading\QuestionGraderInterface;
use App\Services\Grading\TextNormalizer;

/** Each blank accepts several answers; case-insensitive and trimmed. Partial credit per blank. */
class FillBlankGrader implements QuestionGraderInterface
{
    public function grade(Question $question, mixed $answer): GradeResult
    {
        /** @var list<list<string>> $blanks */
        $blanks = $question->answer_key['blanks'] ?? [];
        if ($blanks === []) {
            return GradeResult::wrong();
        }
        $given = is_array($answer) ? array_values($answer) : [$answer];

        $hits = 0;
        $perBlank = [];
        foreach ($blanks as $i => $accepted) {
            $value = TextNormalizer::normalize(is_scalar($given[$i] ?? null) ? (string) $given[$i] : '');
            $ok = $value !== '' && in_array($value, array_map([TextNormalizer::class, 'normalize'], $accepted), true);
            $perBlank[] = $ok;
            $hits += $ok ? 1 : 0;
        }

        return GradeResult::partial($hits / count($blanks), ['blanks' => $perBlank]);
    }

    public function correctAnswer(Question $question): mixed
    {
        return array_map(fn (array $accepted) => $accepted[0] ?? '', $question->answer_key['blanks'] ?? []);
    }
}
