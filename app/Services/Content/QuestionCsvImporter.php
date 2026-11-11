<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionTag;

/**
 * CSV columns: type,skill,level,difficulty,prompt,options,answer,explanation,tags,points
 *  - options: "|"-separated (mcq) ; answer: option letter(s) "a" / "a|c", accepted answers "x|y", or true/false/not_given
 *  - tags: "|"-separated
 */
class QuestionCsvImporter
{
    /** @return array{errors: list<string>, stats: array<string, int>} */
    public function import(string $csv): array
    {
        $errors = [];
        $created = 0;
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)) ?: []);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?? []);
        $levels = Level::query()->pluck('id', 'code');

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $r = array_combine($header, array_pad($row, count($header), '')) ?: [];
            $type = QuestionType::tryFrom(trim($r['type'] ?? ''));
            $skill = Skill::tryFrom(trim($r['skill'] ?? ''));
            if ($type === null || $skill === null || trim($r['prompt'] ?? '') === '') {
                $errors[] = "Baris {$line}: type/skill/prompt tidak valid.";

                continue;
            }
            $options = array_values(array_filter(array_map('trim', explode('|', $r['options'] ?? '')), fn ($v) => $v !== ''));
            $answers = array_values(array_filter(array_map('trim', explode('|', $r['answer'] ?? '')), fn ($v) => $v !== ''));

            [$payload, $key, $error] = $this->build($type, $options, $answers, $r['prompt']);
            if ($error !== null) {
                $errors[] = "Baris {$line}: {$error}";

                continue;
            }

            $question = Question::query()->create([
                'type' => $type,
                'skill' => $skill,
                'level_id' => $levels[strtoupper(str_replace('-', '_', trim($r['level'] ?? '')))] ?? null,
                'difficulty' => max(1, min(5, (int) ($r['difficulty'] ?? 1))),
                'prompt' => trim($r['prompt']),
                'payload' => $payload,
                'answer_key' => $key,
                'explanation' => trim($r['explanation'] ?? '') ?: null,
                'points' => max(1, (int) ($r['points'] ?? 1)),
                'is_active' => true,
            ]);
            $tags = array_filter(array_map('trim', explode('|', $r['tags'] ?? '')));
            $question->tags()->sync(array_map(fn ($t) => QuestionTag::query()->firstOrCreate(['name' => $t])->id, $tags));
            $created++;
        }

        return ['errors' => $errors, 'stats' => ['questions' => $created]];
    }

    /**
     * @param  list<string>  $options
     * @param  list<string>  $answers
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string|null}
     */
    private function build(QuestionType $type, array $options, array $answers, string $prompt): array
    {
        $keyed = array_map(fn ($t, $i) => ['key' => chr(97 + $i), 'text' => $t], $options, array_keys($options));

        return match ($type) {
            QuestionType::McqSingle => count($options) < 2 || count($answers) !== 1
                ? [[], [], 'mcq_single butuh ≥ 2 opsi dan 1 jawaban.']
                : [['options' => $keyed], ['correct' => strtolower($answers[0])], null],
            QuestionType::McqMulti => count($options) < 2 || $answers === []
                ? [[], [], 'mcq_multi butuh opsi dan jawaban.']
                : [['options' => $keyed], ['correct' => array_map('strtolower', $answers), 'partial' => true], null],
            QuestionType::TrueFalseNg => in_array($answers[0] ?? '', ['true', 'false', 'not_given'], true)
                ? [['options' => ['true', 'false', 'not_given']], ['correct' => $answers[0]], null]
                : [[], [], 'jawaban harus true/false/not_given.'],
            QuestionType::FillBlank => $answers === []
                ? [[], [], 'fill_blank butuh jawaban.']
                : [['blanks' => max(1, substr_count($prompt, '___'))], ['blanks' => [$answers]], null],
            QuestionType::Dictation => [['tts_text' => $answers[0] ?? ''], ['text' => $answers[0] ?? ''], $answers === [] ? 'dictation butuh teks.' : null],
            QuestionType::WordOrder => [['tokens' => $options], ['accepted' => $answers], $answers === [] ? 'word_order butuh kalimat diterima.' : null],
            QuestionType::ErrorCorrection, QuestionType::Transformation => [[], ['accepted' => $answers], $answers === [] ? 'butuh kalimat diterima.' : null],
            QuestionType::Speaking => [['task' => 'answer_question', 'max_seconds' => 120], [], null],
            QuestionType::Writing => [['min_words' => 50, 'max_words' => 250], [], null],
            QuestionType::Matching => [[], [], 'matching belum didukung lewat CSV — gunakan JSON.'],
        };
    }
}
