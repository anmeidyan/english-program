<?php

declare(strict_types=1);

use App\Enums\AttemptStatus;
use App\Enums\ProgressStatus;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\ReviewResultAvailable;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\Exam\ExamService;
use App\Services\Review\ReviewService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    Notification::fake();
    $this->course = course(units: 2, lessons: 1, withSpeakingInUnitExam: true);
    $this->user = student();
    initCourse($this->user, $this->course['level']);
    passLesson($this->user, $this->course['lessons'][0][0]);
    $this->exam = $this->course['units'][0]->exam()->first();
});

it('keeps an exam with speaking pending until a reviewer scores it, then unlocks and notifies', function () {
    $service = app(ExamService::class);
    $attempt = $service->start($this->user, $this->exam);
    answerAttempt($attempt, 1.0);
    $speaking = Question::query()->whereIn('id', $attempt->question_ids)->where('type', 'speaking')->firstOrFail();

    $file = UploadedFile::fake()->create('answer.webm', 50, 'audio/webm');
    $this->actingAs($this->user)->post(route('api.attempts.recording', [$attempt, $speaking]), ['audio' => $file, 'transcript' => 'my name is Budi'], ['Accept' => 'application/json'])->assertCreated();

    $result = $service->submit($attempt);
    $progress = app(ProgressRepositoryInterface::class);
    expect($result->status)->toBe(AttemptStatus::PendingReview)
        ->and($progress->status($this->user, $this->course['units'][0]))->toBe(ProgressStatus::PendingReview)
        ->and($progress->status($this->user, $this->course['units'][1]))->toBe(ProgressStatus::Locked);

    // next content stays locked while pending
    $this->actingAs($this->user)->get(route('lessons.show', $this->course['lessons'][1][0]))->assertForbidden();

    $submission = Submission::query()->firstOrFail();
    expect($submission->status)->toBe(SubmissionStatus::Pending)
        ->and($submission->transcript)->toBe('my name is Budi');

    $reviewer = User::factory()->create()->assignRole('reviewer');
    app(ReviewService::class)->grade($submission, $reviewer, ['fluency' => 7, 'pronunciation' => 6, 'grammar' => 7, 'vocabulary' => 7], 'Good job!');

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Passed)
        ->and($progress->status($this->user, $this->course['units'][0]))->toBe(ProgressStatus::Passed)
        ->and($progress->status($this->user, $this->course['units'][1]))->toBe(ProgressStatus::Unlocked);
    Notification::assertSentTo($this->user, ReviewResultAvailable::class);
    expect(AuditLog::query()->where('action', 'submission.graded')->exists())->toBeTrue();
});

it('scores a missing recording as 0 instead of waiting for review', function () {
    $service = app(ExamService::class);
    $attempt = $service->start($this->user, $this->exam);
    answerAttempt($attempt, 1.0);

    expect($service->submit($attempt)->status)->not->toBe(AttemptStatus::PendingReview)
        ->and(Submission::query()->count())->toBe(0);
});

it('serves recordings only via signed URLs to the owner or reviewers', function () {
    $service = app(ExamService::class);
    $attempt = $service->start($this->user, $this->exam);
    $speaking = Question::query()->whereIn('id', $attempt->question_ids)->where('type', 'speaking')->firstOrFail();
    $this->actingAs($this->user)->post(route('api.attempts.recording', [$attempt, $speaking]), ['audio' => UploadedFile::fake()->create('a.webm', 10, 'audio/webm')], ['Accept' => 'application/json'])->assertCreated();
    $service->submit($attempt);
    $submission = Submission::query()->firstOrFail();

    $this->actingAs($this->user)->get(route('recordings.show', $submission))->assertForbidden(); // unsigned
    $signed = URL::temporarySignedRoute('recordings.show', now()->addMinutes(5), $submission);
    $this->actingAs($this->user)->get($signed)->assertOk();
    $this->actingAs(student())->get($signed)->assertForbidden();
});

it('rejects uploads that are not audio', function () {
    $attempt = app(ExamService::class)->start($this->user, $this->exam);
    $speaking = Question::query()->whereIn('id', $attempt->question_ids)->where('type', 'speaking')->firstOrFail();

    $this->actingAs($this->user)->post(route('api.attempts.recording', [$attempt, $speaking]), ['audio' => UploadedFile::fake()->create('x.php', 1, 'application/x-php')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});
