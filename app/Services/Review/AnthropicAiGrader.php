<?php

declare(strict_types=1);

namespace App\Services\Review;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;

/**
 * Optional AI grader via the official Anthropic PHP SDK. Produces a draft only;
 * a reviewer always confirms the final score (PRD 4.5).
 */
class AnthropicAiGrader implements AiGraderInterface
{
    private const DEFAULT_MODEL = 'claude-opus-5-5';

    public function __construct(private readonly ?Client $client = null) {}

    public function draft(Submission $submission): ?array
    {
        $question = $submission->attemptAnswer->question;
        $criteria = Rubric::criteria($submission->type);
        $learnerText = $submission->type === 'speaking' ? (string) $submission->transcript : (string) $submission->text;
        if (trim($learnerText) === '') {
            return null;
        }

        $properties = [];
        foreach ($criteria as $key => $label) {
            $properties[$key] = ['type' => 'integer', 'description' => $label.' band, 0–9'];
        }

        $model = config('englishpath.ai_grader.model') ?: self::DEFAULT_MODEL;
        $client = $this->client ?? new Client(apiKey: (string) config('englishpath.ai_grader.api_key'));

        try {
            $message = $client->beta->messages->create(
                model: $model,
                maxTokens: 4000,
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
                system: 'You are an experienced IELTS examiner grading English learners (CEFR level noted in the task). '
                    .'Score each criterion with an integer band from 0 to 9 and write short, encouraging feedback (max 120 words) '
                    .'that names the two most important things to improve, with one corrected example sentence.',
                messages: [[
                    'role' => 'user',
                    'content' => "Task type: {$submission->type}\nLevel: ".($question->level?->code->value ?? 'unknown')
                        ."\nTask prompt: {$question->prompt}\n\nLearner response".($submission->type === 'speaking' ? ' (speech-to-text transcript)' : '')
                        .":\n\"\"\"\n{$learnerText}\n\"\"\"",
                ]],
                outputConfig: [
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'scores' => ['type' => 'object', 'properties' => $properties, 'required' => array_keys($criteria), 'additionalProperties' => false],
                                'feedback' => ['type' => 'string'],
                            ],
                            'required' => ['scores', 'feedback'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            );
        } catch (APIStatusException $e) {
            Log::warning('AI grader request failed', ['submission' => $submission->id, 'type' => $e->type?->value]);

            return null;
        }

        if ($message->stopReason === 'refusal') {
            return null;
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data) && isset($data['scores'], $data['feedback'])) {
                    $scores = [];
                    foreach (array_keys($criteria) as $key) {
                        $scores[$key] = max(0, min(9, (int) ($data['scores'][$key] ?? 0)));
                    }

                    return ['scores' => $scores, 'feedback' => (string) $data['feedback'], 'model' => $message->model];
                }
            }
        }

        return null;
    }
}
