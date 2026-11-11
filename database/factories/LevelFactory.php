<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LevelCode;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Level> */
class LevelFactory extends Factory
{
    protected $model = Level::class;

    public function definition(): array
    {
        static $i = 0;
        $cases = LevelCode::cases();
        $code = $cases[$i++ % count($cases)];

        return [
            'code' => $code->value,
            'name' => 'Test level '.$code->label(),
            'sort_order' => array_search($code, $cases, true) + 1,
            'estimated_hours' => 10,
            'is_published' => true,
        ];
    }
}
