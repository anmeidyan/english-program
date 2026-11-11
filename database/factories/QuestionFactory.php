<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'type' => QuestionType::McqSingle,
            'skill' => Skill::Grammar,
            'difficulty' => 1,
            'prompt' => 'I ___ a student. '.$this->faker->unique()->numberBetween(1, 1_000_000),
            'payload' => ['options' => [['key' => 'a', 'text' => 'am'], ['key' => 'b', 'text' => 'is'], ['key' => 'c', 'text' => 'are']]],
            'answer_key' => ['correct' => 'a'],
            'explanation' => 'I → am',
            'points' => 1,
            'is_active' => true,
        ];
    }

    public function skill(Skill $skill): static
    {
        return $this->state(['skill' => $skill]);
    }

    public function speaking(): static
    {
        return $this->state([
            'type' => QuestionType::Speaking,
            'skill' => Skill::Speaking,
            'prompt' => 'Introduce yourself.',
            'payload' => ['task' => 'answer_question', 'max_seconds' => 60],
            'answer_key' => [],
            'points' => 2,
        ]);
    }

    public function writing(): static
    {
        return $this->state([
            'type' => QuestionType::Writing,
            'skill' => Skill::Writing,
            'prompt' => 'Write about your family.',
            'payload' => ['min_words' => 10, 'max_words' => 80],
            'answer_key' => [],
            'points' => 2,
        ]);
    }
}
