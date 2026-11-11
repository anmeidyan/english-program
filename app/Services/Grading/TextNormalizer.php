<?php

declare(strict_types=1);

namespace App\Services\Grading;

final class TextNormalizer
{
    private const CONTRACTIONS = [
        "i'm" => 'i am', "you're" => 'you are', "we're" => 'we are', "they're" => 'they are',
        "he's" => 'he is', "she's" => 'she is', "it's" => 'it is', "that's" => 'that is', "there's" => 'there is',
        "isn't" => 'is not', "aren't" => 'are not', "wasn't" => 'was not', "weren't" => 'were not',
        "don't" => 'do not', "doesn't" => 'does not', "didn't" => 'did not',
        "can't" => 'cannot', "won't" => 'will not', "wouldn't" => 'would not', "haven't" => 'have not', "hasn't" => 'has not',
        "i've" => 'i have', "you've" => 'you have', "we've" => 'we have', "they've" => 'they have',
        "i'll" => 'i will', "you'll" => 'you will', "he'll" => 'he will', "she'll" => 'she will', "we'll" => 'we will', "they'll" => 'they will',
        "i'd" => 'i would', "what's" => 'what is', "where's" => 'where is',
    ];

    /** lower-case, unify quotes, trim, collapse whitespace, drop trailing/leading punctuation. */
    public static function normalize(?string $text): string
    {
        $text = (string) $text;
        $text = str_replace(["\u{2019}", "\u{2018}", '`', "\u{201C}", "\u{201D}"], ["'", "'", "'", '"', '"'], $text);
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+([,.!?;:])/u', '$1', $text) ?? $text;

        return trim($text, " \t\n\r\0\x0B.!?;:\"");
    }

    /** Normalised + contractions expanded + all punctuation removed (used for sentence comparison). */
    public static function canonical(?string $text): string
    {
        $text = self::normalize($text);
        $text = strtr($text, self::CONTRACTIONS);
        $text = preg_replace("/[^\p{L}\p{N}' ]+/u", ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** @return list<string> */
    public static function words(?string $text): array
    {
        $canonical = self::canonical($text);

        return $canonical === '' ? [] : explode(' ', $canonical);
    }

    /** Equal after canonicalisation. */
    public static function sameSentence(?string $a, ?string $b): bool
    {
        return self::canonical($a) === self::canonical($b);
    }

    /** @param list<string> $accepted */
    public static function matchesAny(?string $answer, array $accepted): bool
    {
        foreach ($accepted as $candidate) {
            if (self::sameSentence($answer, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Word-level accuracy of `$said` against `$target` (0–1), order-aware (LCS based).
     */
    public static function wordAccuracy(string $target, string $said): float
    {
        $t = self::words($target);
        $s = self::words($said);
        if ($t === []) {
            return 0.0;
        }
        $m = count($t);
        $n = count($s);
        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $dp[$i][$j] = $t[$i - 1] === $s[$j - 1]
                    ? $dp[$i - 1][$j - 1] + 1
                    : max($dp[$i - 1][$j], $dp[$i][$j - 1]);
            }
        }

        return round($dp[$m][$n] / max($m, $n), 4);
    }
}
