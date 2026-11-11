<?php

declare(strict_types=1);

namespace App\Services\Review;

/** PRD 4.5 / 4.6 rubrics, IELTS-style 0–9 band per criterion. */
final class Rubric
{
    public const MAX_BAND = 9;

    /** @return array<string, string> criterion => label */
    public static function criteria(string $type): array
    {
        return $type === 'speaking'
            ? ['fluency' => 'Fluency & coherence', 'pronunciation' => 'Pronunciation', 'grammar' => 'Grammatical range & accuracy', 'vocabulary' => 'Lexical resource']
            : ['task_achievement' => 'Task achievement', 'coherence' => 'Coherence & cohesion', 'lexical_resource' => 'Lexical resource', 'grammatical_range' => 'Grammatical range & accuracy'];
    }

    /** @param array<string, int|float> $scores */
    public static function fraction(string $type, array $scores): float
    {
        $keys = array_keys(self::criteria($type));
        $sum = 0.0;
        foreach ($keys as $key) {
            $sum += max(0, min(self::MAX_BAND, (float) ($scores[$key] ?? 0)));
        }

        return round($sum / (count($keys) * self::MAX_BAND), 4);
    }
}
