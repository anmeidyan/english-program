<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LevelSeeder::class,
            CurriculumSeeder::class,
            PlacementSeeder::class,
            TenseSeeder::class,
            BadgeSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
