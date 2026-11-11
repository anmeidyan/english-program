<?php

declare(strict_types=1);

namespace App\Enums;

enum LevelCode: string
{
    case PreA1 = 'PRE_A1';
    case A1 = 'A1';
    case A2 = 'A2';
    case B1 = 'B1';
    case B2 = 'B2';
    case C1 = 'C1';
    case C2 = 'C2';

    public function label(): string
    {
        return $this === self::PreA1 ? 'Pre-A1' : $this->value;
    }
}
