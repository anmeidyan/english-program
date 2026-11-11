<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Vocabulary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vocabulary> */
class VocabularyFactory extends Factory
{
    protected $model = Vocabulary::class;

    public function definition(): array
    {
        return [
            'word' => $this->faker->unique()->word(),
            'part_of_speech' => 'noun',
            'ipa_us' => '/x/',
            'ipa_uk' => '/x/',
            'meaning_id' => 'arti',
            'definition_en' => 'definition',
            'example' => 'Example.',
        ];
    }
}
