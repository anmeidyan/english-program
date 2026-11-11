<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\FlashcardRating;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewFlashcardRequest;
use App\Models\UserFlashcard;
use App\Models\Vocabulary;
use App\Services\FlashcardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlashcardController extends Controller
{
    public function __construct(private readonly FlashcardService $flashcards) {}

    public function due(Request $request): JsonResponse
    {
        $cards = $this->flashcards->session($request->user())->map(fn (UserFlashcard $card) => [
            'id' => $card->id,
            'is_new' => $card->last_reviewed_at === null,
            'word' => $card->vocabulary->only(['id', 'word', 'part_of_speech', 'ipa_us', 'ipa_uk', 'audio_us', 'audio_uk', 'meaning_id', 'definition_en', 'example', 'image_path']),
        ]);

        return response()->json(['cards' => $cards]);
    }

    public function review(ReviewFlashcardRequest $request, UserFlashcard $card): JsonResponse
    {
        $card = $this->flashcards->review($card, FlashcardRating::from($request->validated('rating')));

        return response()->json(['id' => $card->id, 'due_at' => $card->due_at->toIso8601String(), 'interval_days' => $card->interval_days]);
    }

    public function add(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $card = $this->flashcards->addWord($request->user(), $vocabulary->id);

        return response()->json(['id' => $card->id, 'added' => $card->wasRecentlyCreated], $card->wasRecentlyCreated ? 201 : 200);
    }

    /** Glossary popup lookup (PRD 4.3). */
    public function lookup(Request $request): JsonResponse
    {
        $word = mb_strtolower(trim((string) $request->query('word')));
        $word = preg_replace("/[^a-z' -]/", '', $word) ?? '';
        if ($word === '') {
            return response()->json(['found' => false]);
        }

        $vocab = Vocabulary::query()->where('word', $word)
            ->orWhere('word', rtrim($word, 's'))
            ->first(['id', 'word', 'part_of_speech', 'ipa_us', 'ipa_uk', 'meaning_id', 'definition_en', 'example']);

        return response()->json(['found' => $vocab !== null, 'word' => $vocab]);
    }
}
