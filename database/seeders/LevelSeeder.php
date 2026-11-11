<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LevelCode;
use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [LevelCode::PreA1, 'Pre-A1 Starter', 'Alfabet, angka, sapaan, dan kalimat 3–5 kata.', 40],
            [LevelCode::A1, 'A1 Beginner', 'Memperkenalkan diri, rutinitas, keluarga, rumah, makanan; Simple Present & Present Continuous.', 90],
            [LevelCode::A2, 'A2 Elementary', 'Pengalaman masa lalu, rencana, belanja, perjalanan; Simple Past, Future, comparative.', 120],
            [LevelCode::B1, 'B1 Intermediate', 'Opini, cerita, diskusi; Present Perfect, conditional, passive, reported speech dasar.', 160],
            [LevelCode::B2, 'B2 Upper-Intermediate', 'Essay, argumentasi, listening akademik; 16 tenses lengkap.', 180],
            [LevelCode::C1, 'C1 Advanced', 'IELTS-style writing, presentasi, lecture; inversion, cleft sentence.', 160],
            [LevelCode::C2, 'C2 Proficient', 'Teks sastra/jurnal, debat, writing profesional.', 200],
        ];

        foreach ($levels as $i => [$code, $name, $description, $hours]) {
            Level::query()->updateOrCreate(['code' => $code->value], [
                'name' => $name,
                'description' => $description,
                'sort_order' => $i + 1,
                'estimated_hours' => $hours,
                'is_published' => false,
            ]);
        }
    }
}
