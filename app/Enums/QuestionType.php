<?php

declare(strict_types=1);

namespace App\Enums;

enum QuestionType: string
{
    case McqSingle = 'mcq_single';
    case McqMulti = 'mcq_multi';
    case TrueFalseNg = 'true_false_ng';
    case FillBlank = 'fill_blank';
    case WordOrder = 'word_order';
    case Matching = 'matching';
    case Dictation = 'dictation';
    case ErrorCorrection = 'error_correction';
    case Transformation = 'transformation';
    case Speaking = 'speaking';
    case Writing = 'writing';

    public function label(): string
    {
        return match ($this) {
            self::McqSingle => 'Pilihan ganda (satu jawaban)',
            self::McqMulti => 'Pilihan ganda (banyak jawaban)',
            self::TrueFalseNg => 'True / False / Not Given',
            self::FillBlank => 'Isi bagian kosong',
            self::WordOrder => 'Susun kata',
            self::Matching => 'Jodohkan',
            self::Dictation => 'Dikte (dengar lalu ketik)',
            self::ErrorCorrection => 'Koreksi kesalahan',
            self::Transformation => 'Transformasi kalimat',
            self::Speaking => 'Speaking (rekam)',
            self::Writing => 'Writing (esai)',
        };
    }

    /** Needs a human (or AI + human) to score. */
    public function isManual(): bool
    {
        return $this === self::Speaking || $this === self::Writing;
    }

    /** @return list<string> */
    public static function manualValues(): array
    {
        return [self::Speaking->value, self::Writing->value];
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
