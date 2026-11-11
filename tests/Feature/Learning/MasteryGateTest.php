<?php

declare(strict_types=1);

use App\Enums\ProgressStatus;
use App\Models\User;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\Exam\ExamService;
use App\Services\LessonService;

beforeEach(function () {
    $this->course = course(units: 2, lessons: 2);
    $this->user = student();
    initCourse($this->user, $this->course['level']);
    $this->progress = app(ProgressRepositoryInterface::class);
});

it('gives a new learner only the first lesson of their start level', function () {
    [[$first, $second], [$third]] = $this->course['lessons'];

    expect($this->progress->status($this->user, $first))->toBe(ProgressStatus::Unlocked)
        ->and($this->progress->status($this->user, $second))->toBe(ProgressStatus::Locked)
        ->and($this->progress->status($this->user, $third))->toBe(ProgressStatus::Locked)
        ->and($this->progress->status($this->user, $this->course['units'][1]))->toBe(ProgressStatus::Locked);

    $this->actingAs($this->user)->get(route('lessons.show', $first))->assertOk();
});

it('returns 403 with a pointer to the right item for locked lesson URLs (web and JSON)', function () {
    [[$first, $second]] = $this->course['lessons'];

    $this->actingAs($this->user)->get(route('lessons.show', $second))
        ->assertForbidden()
        ->assertSee(route('lessons.show', $first), false);

    $this->actingAs($this->user)->postJson(route('api.lessons.complete', $second))
        ->assertForbidden()
        ->assertJsonPath('reason', 'locked');

    $this->actingAs($this->user)->getJson(route('api.offline.lesson', $second))->assertForbidden();
});

it('blocks exams of locked content, also through the JSON endpoint', function () {
    $unitExam = $this->course['units'][0]->exam()->first();

    $this->actingAs($this->user)->get(route('exams.show', $unitExam))->assertForbidden();
    $this->actingAs($this->user)->postJson(route('api.attempts.start', $unitExam))->assertForbidden();
});

it('makes the quiz available after the lesson is completed', function () {
    $first = $this->course['lessons'][0][0];
    $quiz = $first->exam()->first();

    $this->actingAs($this->user)->postJson(route('api.attempts.start', $quiz))->assertForbidden()->assertJsonPath('reason', 'not_ready');
    $this->actingAs($this->user)->postJson(route('api.lessons.complete', $first))->assertOk()->assertJsonPath('status', 'exam_ready');
    $this->actingAs($this->user)->postJson(route('api.attempts.start', $quiz))->assertCreated();
});

it('unlocks the next lesson after a quiz ≥ 80% and makes the unit exam ready after the last lesson', function () {
    [[$first, $second]] = $this->course['lessons'];
    $unit = $this->course['units'][0];

    $attempt = passLesson($this->user, $first);
    expect($attempt->passed)->toBeTrue()
        ->and($this->progress->status($this->user, $first))->toBe(ProgressStatus::Passed)
        ->and($this->progress->status($this->user, $second))->toBe(ProgressStatus::Unlocked)
        ->and($this->progress->status($this->user, $unit))->not->toBe(ProgressStatus::ExamReady);

    passLesson($this->user, $second);
    expect($this->progress->status($this->user, $unit))->toBe(ProgressStatus::ExamReady);
});

it('keeps the next lesson locked when the quiz score is below 80%', function () {
    [[$first, $second]] = $this->course['lessons'];
    app(LessonService::class)->complete($this->user, $first);
    $exams = app(ExamService::class);
    $attempt = $exams->start($this->user, $first->exam()->first());
    answerAttempt($attempt, 0.5);
    $result = $exams->submit($attempt);

    expect($result->passed)->toBeFalse()
        ->and($this->progress->status($this->user, $first))->toBe(ProgressStatus::Failed)
        ->and($this->progress->status($this->user, $second))->toBe(ProgressStatus::Locked);

    // lesson quiz: no cooldown, retake immediately
    expect($exams->start($this->user, $first->exam()->first())->id)->not->toBe($attempt->id);
});

it('opens the next unit and its first lesson after the unit exam is passed', function () {
    [[$a, $b], [$c]] = $this->course['lessons'];
    passLesson($this->user, $a);
    passLesson($this->user, $b);

    $exams = app(ExamService::class);
    $attempt = $exams->start($this->user, $this->course['units'][0]->exam()->first());
    answerAttempt($attempt, 1.0);
    expect($exams->submit($attempt)->passed)->toBeTrue()
        ->and($this->progress->status($this->user, $this->course['units'][1]))->toBe(ProgressStatus::Unlocked)
        ->and($this->progress->status($this->user, $c))->toBe(ProgressStatus::Unlocked);
});

it('keeps passed lessons open for review and never lowers their status on practice', function () {
    $first = $this->course['lessons'][0][0];
    passLesson($this->user, $first);

    $q = $first->questions()->first();
    $this->actingAs($this->user)->postJson(route('api.lessons.practice', $first), ['question_id' => $q->id, 'answer' => 'b'])
        ->assertOk()->assertJsonPath('is_correct', false)->assertJsonPath('correct_answer', 'a');

    expect($this->progress->status($this->user, $first))->toBe(ProgressStatus::Passed);
    $this->actingAs($this->user)->get(route('lessons.show', $first))->assertOk();
});

it('lets staff preview any lesson', function () {
    $admin = User::factory()->create()->assignRole('content_admin');
    $locked = $this->course['lessons'][1][0];

    $this->actingAs($admin)->get(route('lessons.show', ['lesson' => $locked, 'preview' => 1]))->assertOk();
});
