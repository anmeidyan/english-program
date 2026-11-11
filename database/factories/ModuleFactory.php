<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Level;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Module> */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'level_id' => Level::factory(),
            'title' => 'Module '.Str::random(5),
            'slug' => 'module-'.Str::lower(Str::random(10)),
            'sort_order' => 1,
            'is_published' => true,
        ];
    }
}
