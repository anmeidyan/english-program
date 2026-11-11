<?php

declare(strict_types=1);

namespace App\Services\Grading;

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Support\Facades\Storage;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds the client-side view of a question. Whitelist only: answer_key, explanation,
 * transcripts and (in exams) audio scripts never leave the server (PRD 5.5, 13.1).
 */
class QuestionPresenter
{
    public const MODE_PRACTICE = 'practice';

    public const MODE_EXAM = 'exam';

    /** @return array<string, mixed> */
    public function present(Question $question, string $mode, int $seed = 0): array
    {
        $payload = $question->payload ?? [];
        $hasScript = ! empty($payload['tts_text']);
        $view = [
            'id' => $question->id,
            'type' => $question->type->value,
            'skill' => $question->skill->value,
            'prompt' => $question->prompt,
            'points' => $question->points,
            'passage' => $payload['passage'] ?? null,
            'media_url' => $question->media_path ? Storage::disk(config('englishpath.media.disk'))->url($question->media_path) : null,
            'has_audio' => $hasScript || $question->media_path !== null,
        ];

        if ($mode === self::MODE_PRACTICE && $hasScript) {
            $view['tts_text'] = $payload['tts_text'];
        }

        $view += match ($question->type) {
            QuestionType::McqSingle, QuestionType::McqMulti => ['options' => $this->shuffle($payload['options'] ?? [], $seed + $question->id)],
            QuestionType::TrueFalseNg => ['options' => ['true', 'false', 'not_given']],
            QuestionType::FillBlank => ['blanks' => (int) ($payload['blanks'] ?? 1)],
            QuestionType::WordOrder => ['tokens' => $this->shuffle($payload['tokens'] ?? [], $seed + $question->id), 'end_punctuation' => $payload['end_punctuation'] ?? '.'],
            QuestionType::Matching => ['left' => $payload['left'] ?? [], 'right' => $this->shuffle($payload['right'] ?? [], $seed + $question->id)],
            QuestionType::ErrorCorrection => ['sentence' => $payload['sentence'] ?? null],
            QuestionType::Speaking => [
                'task' => $payload['task'] ?? 'answer_question',
                // "repeat" must be heard, not read
                'target_text' => ($payload['task'] ?? null) === 'repeat' ? null : ($payload['target_text'] ?? null),
                'max_seconds' => (int) ($payload['max_seconds'] ?? 120),
                'has_audio' => ($payload['task'] ?? null) === 'repeat',
                'tts_text' => $mode === self::MODE_PRACTICE && ($payload['task'] ?? null) === 'repeat' ? ($payload['target_text'] ?? null) : null,
            ],
            QuestionType::Writing => [
                'min_words' => (int) ($payload['min_words'] ?? 0),
                'max_words' => (int) ($payload['max_words'] ?? 0),
                'time_limit_minutes' => (int) ($payload['time_limit_minutes'] ?? 0),
            ],
            default => [],
        };

        return $view;
    }

    /** Script to speak for listening/dictation/repeat items (returned by the audio-play endpoint). */
    public function audioScript(Question $question): ?string
    {
        $payload = $question->payload ?? [];

        return $payload['tts_text'] ?? (($payload['task'] ?? null) === 'repeat' ? ($payload['target_text'] ?? null) : null);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<mixed>
     */
    private function shuffle(array $items, int $seed): array
    {
        if (count($items) < 2) {
            return array_values($items);
        }
        $rng = new Randomizer(new Mt19937($seed));

        return array_values($rng->shuffleArray($items));
    }
}
