<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Content\ContentImportService;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Seeds the A1 curriculum (PRD 7.2): 2 modules, 6 units, 30 lessons, 300+ questions. */
class CurriculumSeeder extends Seeder
{
    public function run(ContentImportService $importer): void
    {
        $unit = fn (string $name): array => require database_path("seeders/content/a1/{$name}.php");

        $report = $importer->importLevel([
            'level' => 'A1',
            'modules' => [
                [
                    'title' => 'A1-M1: Hello, Family & Home',
                    'slug' => 'a1-m1-hello-family-home',
                    'description' => 'Menyapa, keluarga, dan rumah.',
                    'units' => [$unit('u1'), $unit('u2'), $unit('u3')],
                ],
                [
                    'title' => 'A1-M2: Daily Routines',
                    'slug' => 'a1-m2-daily-routines',
                    'description' => 'Rutinitas harian, makanan, dan kegiatan saat ini.',
                    'units' => [$unit('u4'), $unit('u5'), $unit('u6')],
                ],
            ],
        ]);

        if ($report['errors'] !== []) {
            throw new RuntimeException("Curriculum import failed:\n".implode("\n", $report['errors']));
        }

        $this->command?->info('A1 imported: '.json_encode($report['stats']));
    }
}
