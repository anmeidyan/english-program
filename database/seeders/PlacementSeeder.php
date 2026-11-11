<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Skill;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionTag;
use Illuminate\Database\Seeder;

class PlacementSeeder extends Seeder
{
    public function run(): void
    {
        $tag = QuestionTag::query()->firstOrCreate(['name' => 'placement']);
        $bank = require database_path('seeders/content/placement.php');

        foreach ($bank as $code => $items) {
            $level = Level::query()->where('code', $code)->firstOrFail();
            foreach ($items as $i => $item) {
                $question = Question::query()->create($item + [
                    'skill' => Skill::Grammar,
                    'level_id' => $level->id,
                    'is_active' => true,
                ]);
                $question->update(['difficulty' => min(5, $level->sort_order)]);
                $question->tags()->attach($tag->id);
            }
        }
    }
}
