<?php

declare(strict_types=1);

use App\Enums\ProgressStatus;
use App\Enums\RemedialStatus;
use App\Models\RemedialTask;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\Exam\ExamService;

beforeEach(function () {
    $this->course = course(units: 1, lessons: 2);
    $this->user = student();
    initCourse($this->user, $this->course['level']);
    [[$a, $b]] = $this->course['lessons'];
    // make the second lesson a listening lesson so remedial targets it
    $b->update(['skill' => 'listening', 'type' => 'listening']);
    passLesson($this->user, $a);
    passLesson($this->user, $b);
    $this->unit = $this->course['units'][0];
    $this->exam = $this->unit->exam()->first();
    $this->service = app(ExamService::class);
});

it('fails the unit exam on a weak skill, creates remedial tasks and a 1 h cooldown', function () {
    $attempt = $this->service->start($this->user, $this->exam);
    answerAttempt($attempt, ['grammar' => 1.0, 'listening' => 0.5]);
    $result = $this->service->submit($attempt);

    $row = app(ProgressRepositoryInterface::class)->find($this->user, $this->unit);
    expect($result->passed)->toBeFalse()
        ->and($row->status)->toBe(ProgressStatus::Failed)
        ->and($row->cooldown_until->diffInMinutes(now(), true))->toBeGreaterThan(59)->toBeLessThanOrEqual(60)
        ->and(RemedialTask::query()->where('user_id', $this->user->id)->pluck('lesson_id')->all())->toBe([$this->course['lessons'][0][1]->id]);
});

it('refuses a retake during cooldown (server side) and while remedial lessons are pending', function () {
    $attempt = $this->service->start($this->user, $this->exam);
    answerAttempt($attempt, ['grammar' => 1.0, 'listening' => 0.0]);
    $this->service->submit($attempt);

    $this->actingAs($this->user)->postJson(route('api.attempts.start', $this->exam))
        ->assertForbidden()->assertJsonPath('reason', 'cooldown');
    $this->actingAs($this->user)->get(route('exams.show', $this->exam))->assertOk()->assertSee('disabled', false);

    $this->travel(61)->minutes();
    $this->actingAs($this->user)->postJson(route('api.attempts.start', $this->exam))
        ->assertForbidden()->assertJsonPath('reason', 'remedial');

    // pass the remedial lesson's quiz again (≥ 80%)
    $remedialLesson = $this->course['lessons'][0][1];
    $quizAttempt = $this->service->start($this->user, $remedialLesson->exam()->first());
    answerAttempt($quizAttempt, 1.0);
    $this->service->submit($quizAttempt);

    expect(RemedialTask::query()->first()->status)->toBe(RemedialStatus::Completed)
        ->and(app(ProgressRepositoryInterface::class)->status($this->user, $remedialLesson))->toBe(ProgressStatus::Passed);

    $this->actingAs($this->user)->postJson(route('api.attempts.start', $this->exam))->assertCreated();
});

it('escalates the cooldown 1 h → 6 h → 24 h', function () {
    $expected = [60, 360, 1440, 1440];
    foreach ($expected as $minutes) {
        $attempt = $this->service->start($this->user, $this->exam);
        answerAttempt($attempt, 0.0);
        $this->service->submit($attempt);
        $row = app(ProgressRepositoryInterface::class)->find($this->user, $this->unit);
        expect((int) round(now()->diffInMinutes($row->cooldown_until)))->toBe($minutes);

        RemedialTask::query()->update(['status' => RemedialStatus::Completed->value]);
        $this->travel($minutes + 1)->minutes();
    }
});

it('draws a different question set on retake (overlap ≤ 30% with a large pool)', function () {
    $first = $this->service->start($this->user, $this->exam);
    answerAttempt($first, 0.0);
    $this->service->submit($first);
    RemedialTask::query()->update(['status' => RemedialStatus::Completed->value]);
    $this->travel(2)->hours();

    $second = $this->service->start($this->user, $this->exam);
    $overlap = count(array_intersect($first->question_ids, $second->question_ids)) / count($second->question_ids);

    expect($overlap)->toBeLessThanOrEqual(0.3)
        ->and($second->question_ids)->toHaveCount(count(array_unique($second->question_ids)));
});
