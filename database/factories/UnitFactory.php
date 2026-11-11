<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => 'Unit '.Str::random(5),
            'slug' => 'unit-'.Str::lower(Str::random(10)),
            'sort_order' => 1,
            'is_published' => true,
        ];
    }
}
