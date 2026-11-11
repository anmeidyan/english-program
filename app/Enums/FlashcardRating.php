<?php

declare(strict_types=1);

namespace App\Enums;

enum FlashcardRating: string
{
    case Again = 'again';
    case Hard = 'hard';
    case Good = 'good';
    case Easy = 'easy';

    /** SM-2 quality (0–5). */
    public function quality(): int
    {
        return match ($this) {
            self::Again => 1,
            self::Hard => 3,
            self::Good => 4,
            self::Easy => 5,
        };
    }
}
