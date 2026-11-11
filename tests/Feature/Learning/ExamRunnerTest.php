<?php

declare(strict_types=1);

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\Exam\ExamService;
use App\Services\LessonService;

beforeEach(function () {
    $this->course = course(units: 1, lessons: 1);
    $this->user = student();
    initCourse($this->user, $this->course['level']);
    $this->lesson = $this->course['lessons'][0][0];
    app(LessonService::class)->complete($this->user, $this->lesson);
    $this->quiz = $this->lesson->exam()->first();
});

it('never sends answer keys or explanations to the client', function () {
    $response = $this->actingAs($this->user)->postJson(route('api.attempts.start', $this->quiz))->assertCreated();
    $attempt = ExamAttempt::query()->latest('id')->first();

    $json = $response->getContent();
    expect($json)->not->toContain('answer_key')->not->toContain('"correct"')->not->toContain('explanation');

    $runner = $this->actingAs($this->user)->get(route('exams.run', $attempt))->assertOk()->getContent();
    expect($runner)->not->toContain('answer_key')->not->toContain('I → am');

    $show = $this->actingAs($this->user)->getJson(route('api.attempts.show', $attempt))->assertOk()->getContent();
    expect($show)->not->toContain('answer_key');
});

it('autosaves answers and grades them on submit', function () {
    $attempt = app(ExamService::class)->start($this->user, $this->quiz);
    foreach ($attempt->question_ids as $id) {
        $this->actingAs($this->user)->patchJson(route('api.attempts.answer', [$attempt, $id]), ['answer' => 'a'])->assertOk();
    }
    $this->actingAs($this->user)->postJson(route('api.attempts.submit', $attempt))
        ->assertOk()->assertJsonPath('passed', true)->assertJsonPath('status', 'passed');

    expect($attempt->fresh()->score)->toBe('100.00');
});

it('rejects answers for questions outside the attempt and other users\' attempts', function () {
    $attempt = app(ExamService::class)->start($this->user, $this->quiz);
    $outside = Question::factory()->create();

    $this->actingAs($this->user)->patchJson(route('api.attempts.answer', [$attempt, $outside]), ['answer' => 'a'])->assertNotFound();
    $this->actingAs(student())->patchJson(route('api.attempts.answer', [$attempt, $attempt->question_ids[0]]), ['answer' => 'a'])->assertForbidden();
});

it('keeps a single active attempt per learner and exam and resumes it', function () {
    $service = app(ExamService::class);
    $first = $service->start($this->user, $this->quiz);
    $second = $service->start($this->user, $this->quiz);

    expect($second->id)->toBe($first->id)
        ->and(ExamAttempt::query()->where('active_key', "{$this->user->id}:{$this->quiz->id}")->count())->toBe(1);
});

it('refuses a submit after expires_at + 30 s but still grades the autosaved answers', function () {
    $this->quiz->update(['duration_minutes' => 10]);
    $service = app(ExamService::class);
    $attempt = $service->start($this->user, $this->quiz);
    answerAttempt($attempt, 1.0);

    $this->travel(10)->minutes();
    $this->travel(31)->seconds();

    $this->actingAs($this->user)->patchJson(route('api.attempts.answer', [$attempt, $attempt->question_ids[0]]), ['answer' => 'b'])->assertStatus(422);
    $this->actingAs($this->user)->postJson(route('api.attempts.submit', $attempt))->assertStatus(422);

    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe(AttemptStatus::Passed)
        ->and((float) $fresh->score)->toBe(100.0);
});

it('accepts a submit inside the 30 s grace window', function () {
    $this->quiz->update(['duration_minutes' => 10]);
    $attempt = app(ExamService::class)->start($this->user, $this->quiz);
    answerAttempt($attempt, 1.0);
    $this->travel(10)->minutes();
    $this->travel(20)->seconds();

    $this->actingAs($this->user)->postJson(route('api.attempts.submit', $attempt))->assertOk();
});

it('finalises expired attempts from the scheduler command', function () {
    $this->quiz->update(['duration_minutes' => 5]);
    $attempt = app(ExamService::class)->start($this->user, $this->quiz);
    $this->travel(6)->minutes();

    $this->artisan('exams:finalize-expired')->assertSuccessful();
    expect($attempt->fresh()->status)->toBe(AttemptStatus::Failed)
        ->and((float) $attempt->fresh()->score)->toBe(0.0);
});

it('limits listening audio plays on the server', function () {
    $this->quiz->update(['max_audio_plays' => 2]);
    $attempt = app(ExamService::class)->start($this->user, $this->quiz);
    $question = Question::find($attempt->question_ids[0]);
    $question->update(['payload' => $question->payload + ['tts_text' => 'Hello there']]);

    $this->actingAs($this->user)->postJson(route('api.attempts.audio', [$attempt, $question]))->assertOk()->assertJsonPath('plays', 1)->assertJsonPath('tts_text', 'Hello there');
    $this->actingAs($this->user)->postJson(route('api.attempts.audio', [$attempt, $question]))->assertOk()->assertJsonPath('plays', 2);
    $this->actingAs($this->user)->postJson(route('api.attempts.audio', [$attempt, $question]))->assertStatus(429);
});

it('logs integrity events without failing the attempt', function () {
    $attempt = app(ExamService::class)->start($this->user, $this->quiz);
    $this->actingAs($this->user)->postJson(route('api.attempts.events', $attempt), ['type' => 'visibility_hidden'])->assertOk();
    $this->actingAs($this->user)->postJson(route('api.attempts.events', $attempt), ['type' => 'paste'])->assertOk();

    expect(collect($attempt->fresh()->integrity_log)->pluck('type')->all())->toBe(['visibility_hidden', 'paste'])
        ->and($attempt->fresh()->status)->toBe(AttemptStatus::InProgress);
});

it('requires a verified email before the first exam', function () {
    $this->user->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($this->user)->postJson(route('api.attempts.start', $this->quiz))->assertForbidden();
});
