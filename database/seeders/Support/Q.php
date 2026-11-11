<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Compact builders for seed questions. Each returns an array matching the `questions` table
 * (type, prompt, payload, answer_key, explanation, difficulty). Skill/level/tags are filled by the seeder.
 */
final class Q
{
    /** @param list<string> $options */
    public static function mcq(string $prompt, array $options, int $correct, string $explanation = '', int $difficulty = 1): array
    {
        return self::make('mcq_single', $prompt, ['options' => self::keyed($options)], ['correct' => self::key($correct)], $explanation, $difficulty);
    }

    /**
     * @param  list<string>  $options
     * @param  list<int>  $correct
     */
    public static function multi(string $prompt, array $options, array $correct, string $explanation = '', int $difficulty = 2): array
    {
        return self::make('mcq_multi', $prompt, ['options' => self::keyed($options)], [
            'correct' => array_map(fn (int $i) => self::key($i), $correct),
            'partial' => true,
        ], $explanation, $difficulty);
    }

    /** @param 'true'|'false'|'not_given' $answer */
    public static function tf(string $statement, string $answer, string $explanation = '', int $difficulty = 2): array
    {
        return self::make('true_false_ng', $statement, ['options' => ['true', 'false', 'not_given']], ['correct' => $answer], $explanation, $difficulty);
    }

    /**
     * Prompt uses ___ for each blank.
     *
     * @param  list<list<string>>  $blanks  accepted answers per blank
     */
    public static function fill(string $prompt, array $blanks, string $explanation = '', int $difficulty = 1): array
    {
        return self::make('fill_blank', $prompt, ['blanks' => count($blanks)], ['blanks' => $blanks], $explanation, $difficulty);
    }

    /** @param list<string> $alsoAccepted */
    public static function order(string $sentence, string $explanation = '', int $difficulty = 1, array $alsoAccepted = []): array
    {
        $tokens = preg_split('/\s+/', trim(preg_replace('/[.?!]$/', '', $sentence) ?? $sentence)) ?: [];
        $shuffled = self::deterministicShuffle($tokens, $sentence);

        return self::make('word_order', 'Susun kata-kata berikut menjadi kalimat yang benar.', [
            'tokens' => $shuffled,
            'end_punctuation' => preg_match('/([.?!])$/', $sentence, $m) ? $m[1] : '.',
        ], ['accepted' => array_values(array_unique([$sentence, ...$alsoAccepted]))], $explanation, $difficulty);
    }

    /** @param list<array{0:string,1:string}> $pairs */
    public static function match(array $pairs, string $prompt = 'Jodohkan kata dengan artinya.', string $explanation = '', int $difficulty = 1): array
    {
        $left = [];
        $right = [];
        $key = [];
        foreach ($pairs as $i => [$l, $r]) {
            $left[] = ['key' => 'l'.($i + 1), 'text' => $l];
            $right[] = ['key' => 'r'.($i + 1), 'text' => $r];
            $key['l'.($i + 1)] = 'r'.($i + 1);
        }

        return self::make('matching', $prompt, [
            'left' => $left,
            'right' => self::deterministicShuffle($right, $prompt.json_encode($pairs)),
        ], ['pairs' => $key], $explanation, $difficulty);
    }

    public static function dict(string $text, string $explanation = '', int $difficulty = 2, string $prompt = 'Dengarkan lalu ketik apa yang kamu dengar.'): array
    {
        return self::make('dictation', $prompt, ['tts_text' => $text], ['text' => $text], $explanation, $difficulty);
    }

    /** @param list<string> $accepted */
    public static function err(string $wrong, array $accepted, string $explanation = '', int $difficulty = 2): array
    {
        return self::make('error_correction', 'Temukan dan perbaiki kesalahan pada kalimat berikut: "'.$wrong.'"', ['sentence' => $wrong], ['accepted' => $accepted], $explanation, $difficulty);
    }

    /** @param list<string> $accepted */
    public static function trans(string $prompt, array $accepted, string $explanation = '', int $difficulty = 2): array
    {
        return self::make('transformation', $prompt, [], ['accepted' => $accepted], $explanation, $difficulty);
    }

    public static function speak(string $prompt, string $task, ?string $target = null, int $maxSeconds = 60, int $difficulty = 2): array
    {
        return self::make('speaking', $prompt, array_filter([
            'task' => $task,
            'target_text' => $target,
            'max_seconds' => $maxSeconds,
        ], fn ($v) => $v !== null), ['target_text' => $target], '', $difficulty, 2);
    }

    public static function write(string $prompt, int $minWords, int $maxWords, int $timeLimitMinutes = 15, int $difficulty = 2): array
    {
        return self::make('writing', $prompt, [
            'min_words' => $minWords,
            'max_words' => $maxWords,
            'time_limit_minutes' => $timeLimitMinutes,
        ], [], '', $difficulty, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answerKey
     */
    private static function make(string $type, string $prompt, array $payload, array $answerKey, string $explanation, int $difficulty, int $points = 1): array
    {
        return [
            'type' => $type,
            'prompt' => $prompt,
            'payload' => $payload,
            'answer_key' => $answerKey,
            'explanation' => $explanation !== '' ? $explanation : null,
            'difficulty' => $difficulty,
            'points' => $points,
        ];
    }

    /**
     * @param  list<string>  $options
     * @return list<array{key:string,text:string}>
     */
    private static function keyed(array $options): array
    {
        return array_map(fn (string $text, int $i) => ['key' => self::key($i), 'text' => $text], $options, array_keys($options));
    }

    private static function key(int $i): string
    {
        return chr(97 + $i);
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private static function deterministicShuffle(array $items, string $seed): array
    {
        if (count($items) < 2) {
            return $items;
        }
        mt_srand(crc32($seed));
        $out = $items;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            shuffle($out);
            if ($out !== $items) {
                break;
            }
        }
        mt_srand();

        return $out;
    }
}
