<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LessonType;
use App\Enums\Skill;
use App\Models\Lesson;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Lesson> */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'title' => 'Lesson '.Str::random(5),
            'slug' => 'lesson-'.Str::lower(Str::random(10)),
            'type' => LessonType::Grammar,
            'skill' => Skill::Grammar,
            'content' => [['type' => 'heading', 'data' => ['text' => 'Hello']]],
            'estimated_minutes' => 15,
            'sort_order' => 1,
            'is_published' => true,
        ];
    }
}
