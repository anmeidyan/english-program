<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/** Lesson content block builders (PRD 7.1 lesson builder block types). */
final class B
{
    public static function heading(string $text): array
    {
        return ['type' => 'heading', 'data' => ['text' => $text]];
    }

    public static function text(string $html): array
    {
        return ['type' => 'rich_text', 'data' => ['html' => $html]];
    }

    public static function formula(string $positive, string $negative, string $question, ?string $note = null): array
    {
        return ['type' => 'tense_formula', 'data' => compact('positive', 'negative', 'question') + ['note' => $note]];
    }

    /** @param list<array{0:string,1?:string}> $items [english, indonesian] */
    public static function examples(array $items, ?string $title = null): array
    {
        return ['type' => 'example_list', 'data' => [
            'title' => $title,
            'items' => array_map(fn (array $i) => ['en' => $i[0], 'id' => $i[1] ?? null], $items),
        ]];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public static function table(array $headers, array $rows, ?string $caption = null): array
    {
        return ['type' => 'table', 'data' => compact('headers', 'rows', 'caption')];
    }

    public static function tip(string $text): array
    {
        return ['type' => 'tip', 'data' => ['text' => $text]];
    }

    public static function mistake(string $wrong, string $right, string $note): array
    {
        return ['type' => 'common_mistake', 'data' => compact('wrong', 'right', 'note')];
    }

    public static function passage(string $title, string $text): array
    {
        return ['type' => 'reading_passage', 'data' => compact('title', 'text')];
    }

    /** Audio block; when no file exists, the player speaks `tts_text` with the browser's speech engine. */
    public static function audio(string $caption, string $ttsText, ?string $url = null, string $accent = 'en-US'): array
    {
        return ['type' => 'audio', 'data' => ['caption' => $caption, 'url' => $url, 'tts_text' => $ttsText, 'accent' => $accent, 'transcript' => $ttsText]];
    }

    /** @param list<string> $points */
    public static function timeline(string $label, string $position, array $points = []): array
    {
        return ['type' => 'timeline', 'data' => compact('label', 'position', 'points')];
    }
}
