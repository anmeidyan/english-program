<?php

declare(strict_types=1);

namespace App\Enums;

enum Skill: string
{
    case Grammar = 'grammar';
    case Vocabulary = 'vocabulary';
    case Reading = 'reading';
    case Listening = 'listening';
    case Speaking = 'speaking';
    case Writing = 'writing';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
