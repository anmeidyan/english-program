<?php

declare(strict_types=1);

use App\Enums\FlashcardRating;
use App\Models\UserFlashcard;
use App\Models\Vocabulary;
use App\Services\FlashcardService;

it('applies SM-2 intervals and ease factor', function () {
    $user = student();
    $service = app(FlashcardService::class);
    $card = $service->addWord($user, Vocabulary::factory()->create()->id);

    $service->review($card, FlashcardRating::Good);
    expect($card->fresh()->interval_days)->toBe(1)->and($card->fresh()->repetitions)->toBe(1);

    $service->review($card->fresh(), FlashcardRating::Good);
    expect($card->fresh()->interval_days)->toBe(6);

    $service->review($card->fresh(), FlashcardRating::Good);
    expect($card->fresh()->interval_days)->toBe(15); // 6 × 2.5

    $service->review($card->fresh(), FlashcardRating::Again);
    $fresh = $card->fresh();
    expect($fresh->repetitions)->toBe(0)
        ->and($fresh->ease_factor)->toBeLessThan(2.5)
        ->and($fresh->due_at->lessThan(now()->addMinutes(11)))->toBeTrue();
});

it('never drops the ease factor below 1.3', function () {
    $user = student();
    $service = app(FlashcardService::class);
    $card = $service->addWord($user, Vocabulary::factory()->create()->id);
    foreach (range(1, 10) as $_) {
        $service->review($card->fresh(), FlashcardRating::Again);
    }
    expect($card->fresh()->ease_factor)->toBe(1.3);
});

it('serves at most 50 due + 10 new cards and only the owner can review', function () {
    $user = student();
    $words = Vocabulary::factory()->count(70)->create();
    foreach ($words as $i => $w) {
        UserFlashcard::query()->create([
            'user_id' => $user->id, 'vocabulary_id' => $w->id, 'ease_factor' => 2.5, 'interval_days' => 1, 'repetitions' => $i < 55 ? 1 : 0,
            'due_at' => now()->subDay(), 'last_reviewed_at' => $i < 55 ? now()->subDays(2) : null,
        ]);
    }

    $cards = $this->actingAs($user)->getJson(route('api.flashcards.due'))->assertOk()->json('cards');
    expect($cards)->toHaveCount(60)
        ->and(collect($cards)->where('is_new', true))->toHaveCount(10);

    $card = UserFlashcard::query()->first();
    $this->actingAs(student())->postJson(route('api.flashcards.review', $card), ['rating' => 'good'])->assertForbidden();
    $this->actingAs($user)->postJson(route('api.flashcards.review', $card), ['rating' => 'good'])->assertOk();
});

it('adds lesson words to the deck when a lesson is completed', function () {
    $course = course(units: 1, lessons: 1);
    $lesson = $course['lessons'][0][0];
    $lesson->vocabularies()->attach(Vocabulary::factory()->count(3)->create()->pluck('id'));
    $user = student();
    initCourse($user, $course['level']);

    $this->actingAs($user)->postJson(route('api.lessons.complete', $lesson))->assertOk();
    expect(UserFlashcard::query()->where('user_id', $user->id)->count())->toBe(3);
});
