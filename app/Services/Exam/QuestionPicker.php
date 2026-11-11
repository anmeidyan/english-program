<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestionRule;
use App\Models\Question;
use App\Models\User;
use Random\Engine\Secure;
use Random\Randomizer;

/**
 * Draws a fresh random set for every attempt (PRD 5.3): per rule, questions this learner has
 * not seen in this exam come first; previously seen ones only fill the gap. No duplicates.
 */
class QuestionPicker
{
    private Randomizer $random;

    public function __construct(?Randomizer $random = null)
    {
        $this->random = $random ?? new Randomizer(new Secure);
    }

    /** @return list<int> */
    public function pick(Exam $exam, User $user): array
    {
        $seen = ExamAttempt::query()->where('exam_id', $exam->id)->where('user_id', $user->id)
            ->pluck('question_ids')->flatten()->map(fn ($id) => (int) $id)->unique()->all();
        $seen = array_flip($seen);

        $chosen = [];
        $rules = $exam->rules()->get();
        foreach ($rules as $rule) {
            $candidates = array_values(array_diff($this->candidates($rule), $chosen));
            $fresh = array_values(array_filter($candidates, fn (int $id) => ! isset($seen[$id])));
            $used = array_values(array_filter($candidates, fn (int $id) => isset($seen[$id])));

            $take = [...$this->shuffled($fresh), ...$this->shuffled($used)];
            $chosen = [...$chosen, ...array_slice($take, 0, $rule->count)];
        }

        return $this->shuffled($chosen);
    }

    /** @return list<int> */
    public function candidates(ExamQuestionRule $rule): array
    {
        return Question::query()->active()
            ->when($rule->tag_id, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('question_tags.id', $rule->tag_id)))
            ->when($rule->skill, fn ($q) => $q->where('skill', $rule->skill->value))
            ->when(
                $rule->question_type,
                fn ($q) => $q->where('type', $rule->question_type->value),
                fn ($q) => $q->whereNotIn('type', QuestionType::manualValues()),
            )
            ->whereBetween('difficulty', [$rule->difficulty_min, $rule->difficulty_max])
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $items
     * @return list<int>
     */
    private function shuffled(array $items): array
    {
        return $items === [] ? [] : array_values($this->random->shuffleArray($items));
    }
}
