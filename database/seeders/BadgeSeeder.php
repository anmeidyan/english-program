<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['first-lesson', 'First Lesson', 'Lulus lesson pertama', '🌱', ['type' => 'lessons_passed', 'value' => 1]],
            ['ten-lessons', '10 Lessons', 'Lulus 10 lesson', '📚', ['type' => 'lessons_passed', 'value' => 10]],
            ['streak-7', '7-Day Streak', 'Belajar 7 hari berturut-turut', '🔥', ['type' => 'streak', 'value' => 7]],
            ['streak-30', '30-Day Streak', 'Belajar 30 hari berturut-turut', '🏆', ['type' => 'streak', 'value' => 30]],
            ['tenses-master', 'Tenses Master', 'Lulus semua checkpoint Tenses Master', '⏳', ['type' => 'tenses_master']],
            ['a1-graduate', 'A1 Graduate', 'Lulus Level Exam A1', '🎓', ['type' => 'level_passed', 'value' => 'A1']],
            ['a2-graduate', 'A2 Graduate', 'Lulus Level Exam A2', '🎓', ['type' => 'level_passed', 'value' => 'A2']],
            ['b1-graduate', 'B1 Graduate', 'Lulus Level Exam B1', '🎓', ['type' => 'level_passed', 'value' => 'B1']],
            ['words-100', '100 Words', '100 kata dipelajari di flashcard', '🃏', ['type' => 'words', 'value' => 100]],
            ['words-1000', '1000 Words', '1000 kata dipelajari di flashcard', '🧠', ['type' => 'words', 'value' => 1000]],
        ];

        foreach ($badges as [$code, $name, $description, $icon, $criteria]) {
            Badge::query()->updateOrCreate(['code' => $code], compact('name', 'description', 'icon', 'criteria'));
        }
    }
}
