<?php

declare(strict_types=1);

namespace App\Services\Speaking;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Illuminate\Support\Facades\Log;

/**
 * Conversation partner + corrector via Claude (AI_GRADER_DRIVER=anthropic). Returns structured
 * JSON so the UI can highlight each correction; any failure returns null and the caller falls back
 * to the offline rule-based coach.
 */
class AnthropicSpeakingCoach implements SpeakingCoachInterface
{
    private const DEFAULT_MODEL = 'claude-opus-5-5';

    private const SYSTEM = <<<'TXT'
        You are a friendly English speaking coach for Indonesian learners practising conversation every day.
        The learner's turn is a speech-to-text transcript, so ignore punctuation, capitalisation and filler words
        ("um", "uh") unless they change the meaning. Speech recognition can mishear words; if a word looks like a
        recognition error rather than a learner mistake, do not count it.

        For each learner turn:
        - "corrected": the learner's turn rewritten as a natural, correct sentence a fluent speaker would say. Keep
          their meaning and as many of their words as possible. If it is already correct, repeat it unchanged.
        - "corrections": one entry per real mistake (grammar, vocabulary, word_choice, or fluency for unnatural but
          correct phrasing). "original" and "corrected" are the short fragments that changed. "explanation" is one
          short sentence in Bahasa Indonesia explaining the rule. Empty list when there are no mistakes.
        - "score": 0-100 for accuracy and fluency at the learner's CEFR level (90+ only for correct, natural answers
          of reasonable length; very short answers score lower).
        - "tip": one short, encouraging sentence in Bahasa Indonesia about the single most useful thing to improve
          next, or a useful phrase to try.
        - "reply": your next turn in the conversation, in English: briefly react to what they said, then ask one
          follow-up question about the topic. Match the learner's level (simple words and short sentences for
          Pre-A1 to A2). Max 40 words. Do not mention the corrections in the reply.
        TXT;

    public function __construct(private readonly ?Client $client = null) {}

    public function respond(array $topic, string $levelCode, array $history, string $transcript): ?CoachResult
    {
        $conversation = "Coach: {$topic['opener']}\n";
        foreach ($history as $turn) {
            $conversation .= "Learner: {$turn['learner']}\nCoach: {$turn['coach']}\n";
        }

        $model = config('englishpath.ai_grader.model') ?: self::DEFAULT_MODEL;
        $client = $this->client ?? new Client(apiKey: (string) config('englishpath.ai_grader.api_key'));

        try {
            $message = $client->beta->messages->create(
                model: $model,
                maxTokens: 4000,
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
                system: self::SYSTEM,
                messages: [[
                    'role' => 'user',
                    'content' => "Learner level: {$levelCode}\nTopic: {$topic['title']}\n\nConversation so far:\n{$conversation}\n"
                        ."Learner's new turn (speech-to-text):\n\"\"\"\n{$transcript}\n\"\"\"",
                ]],
                outputConfig: [
                    'effort' => 'low',
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'corrected' => ['type' => 'string'],
                                'corrections' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'original' => ['type' => 'string'],
                                            'corrected' => ['type' => 'string'],
                                            'explanation' => ['type' => 'string'],
                                            'type' => ['type' => 'string', 'enum' => ['grammar', 'vocabulary', 'word_choice', 'fluency']],
                                        ],
                                        'required' => ['original', 'corrected', 'explanation', 'type'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                                'score' => ['type' => 'integer'],
                                'tip' => ['type' => 'string'],
                                'reply' => ['type' => 'string'],
                            ],
                            'required' => ['corrected', 'corrections', 'score', 'tip', 'reply'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            );
        } catch (APIStatusException $e) {
            Log::warning('Speaking coach request failed', ['status' => $e->status ?? null, 'type' => $e->type?->value]);

            return null;
        } catch (APIConnectionException $e) {
            Log::warning('Speaking coach connection failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($message->stopReason === 'refusal' || $message->stopReason === 'max_tokens') {
            return null;
        }

        foreach ($message->content as $block) {
            if ($block->type !== 'text') {
                continue;
            }
            $data = json_decode($block->text, true);
            if (! is_array($data) || ! isset($data['corrected'], $data['reply'])) {
                return null;
            }
            $corrections = [];
            foreach ((array) ($data['corrections'] ?? []) as $c) {
                if (is_array($c) && isset($c['original'], $c['corrected'])) {
                    $corrections[] = [
                        'original' => (string) $c['original'],
                        'corrected' => (string) $c['corrected'],
                        'explanation' => (string) ($c['explanation'] ?? ''),
                        'type' => (string) ($c['type'] ?? 'grammar'),
                    ];
                }
            }

            return new CoachResult(
                corrected: (string) $data['corrected'],
                score: max(0, min(100, (int) ($data['score'] ?? 0))),
                corrections: $corrections,
                tip: isset($data['tip']) ? (string) $data['tip'] : null,
                reply: (string) $data['reply'],
                coach: 'claude:'.$message->model,
            );
        }

        return null;
    }
}
