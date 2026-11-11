<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Models\AttemptAnswer;
use App\Models\Exam;
use Illuminate\Support\Collection;

/**
 * PRD 5.2: score = points earned / total points × 100; pass needs the total ≥ passing score
 * AND every examined skill ≥ the minimum skill score.
 */
class ScoringService
{
    /**
     * @param  Collection<int, AttemptAnswer>  $answers  with `question` loaded
     * @return array{score: float, skill_scores: array<string, float>, pending: int}
     */
    public function compute(Collection $answers): array
    {
        $earned = 0.0;
        $total = 0;
        $perSkill = [];
        $pending = 0;

        foreach ($answers as $answer) {
            $question = $answer->question;
            $points = max(1, $question->points);
            $skill = $question->skill->value;
            $perSkill[$skill] ??= ['earned' => 0.0, 'total' => 0];
            $perSkill[$skill]['total'] += $points;
            $total += $points;

            if ($answer->is_correct === null && $question->type->isManual() && $answer->graded_by?->value !== 'reviewer') {
                $pending++;

                continue;
            }

            $earned += (float) $answer->points_awarded;
            $perSkill[$skill]['earned'] += (float) $answer->points_awarded;
        }

        $skillScores = [];
        foreach ($perSkill as $skill => $row) {
            $skillScores[$skill] = $row['total'] > 0 ? round($row['earned'] / $row['total'] * 100, 2) : 0.0;
        }

        return [
            'score' => $total > 0 ? round($earned / $total * 100, 2) : 0.0,
            'skill_scores' => $skillScores,
            'pending' => $pending,
        ];
    }

    /** @param array<string, float> $skillScores */
    public function passes(Exam $exam, float $score, array $skillScores): bool
    {
        if ($score < $exam->passing_score) {
            return false;
        }
        if ($exam->min_skill_score > 0) {
            foreach ($skillScores as $value) {
                if ($value < $exam->min_skill_score) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Skills that caused the failure (below the per-skill minimum, else below the passing score).
     *
     * @param  array<string, float>  $skillScores
     * @return list<string>
     */
    public function weakSkills(Exam $exam, array $skillScores): array
    {
        $threshold = $exam->min_skill_score > 0 ? $exam->min_skill_score : $exam->passing_score;
        $weak = array_keys(array_filter($skillScores, fn (float $v) => $v < $threshold));
        if ($weak === [] && $skillScores !== []) {
            $weak = array_keys(array_filter($skillScores, fn (float $v) => $v < $exam->passing_score));
        }

        return array_values(array_map('strval', $weak));
    }
}
