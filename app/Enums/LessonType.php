<?php

declare(strict_types=1);

namespace App\Enums;

enum LessonType: string
{
    case Grammar = 'grammar';
    case Vocabulary = 'vocabulary';
    case Reading = 'reading';
    case Listening = 'listening';
    case Speaking = 'speaking';
    case Writing = 'writing';
    case Pronunciation = 'pronunciation';
    case Review = 'review';

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = ucfirst($case->value);
        }

        return $out;
    }
}
