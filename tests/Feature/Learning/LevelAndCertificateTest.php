<?php

declare(strict_types=1);

use App\Enums\ProgressStatus;
use App\Models\Certificate;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Module;
use App\Models\Unit;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\CurriculumService;
use App\Services\Exam\ExamService;
use App\Services\Progress\UnlockService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('issues a verifiable PDF certificate and opens the next level after the level exam', function () {
    Storage::fake('local');
    Notification::fake();
    $course = course(units: 1, lessons: 1);
    $next = Level::factory()->create(['sort_order' => 99]);
    $nextLesson = Lesson::factory()->create(['unit_id' => Unit::factory()->create(['module_id' => Module::factory()->create(['level_id' => $next->id])->id])->id]);
    app(CurriculumService::class)->flush();

    $user = student();
    initCourse($user, $course['level']);
    passLesson($user, $course['lessons'][0][0]);
    $service = app(ExamService::class);
    $unitAttempt = $service->start($user, $course['units'][0]->exam()->first());
    answerAttempt($unitAttempt, 1.0);
    $service->submit($unitAttempt);

    $progress = app(ProgressRepositoryInterface::class);
    expect($progress->status($user, $course['level']))->toBe(ProgressStatus::ExamReady);

    $attempt = $service->start($user, $course['level']->exam()->first());
    answerAttempt($attempt, 1.0);
    expect($service->submit($attempt)->passed)->toBeTrue();

    $certificate = Certificate::query()->where('user_id', $user->id)->firstOrFail();
    Storage::disk('local')->assertExists($certificate->pdf_path);
    expect($progress->status($user, $next))->toBe(ProgressStatus::Unlocked)
        ->and($progress->status($user, $nextLesson))->toBe(ProgressStatus::Unlocked)
        ->and($user->fresh()->current_level_id)->toBe($next->id);

    $this->get(route('certificates.verify', $certificate->code))->assertOk()->assertSee($user->name)->assertSee('valid');
    $this->get(route('certificates.verify', 'EP-NOPE'))->assertOk()->assertSee('tidak ditemukan');
    $this->actingAs($user)->get(route('certificates.download', $certificate))->assertOk();
    $this->actingAs(student())->get(route('certificates.download', $certificate))->assertForbidden();

    // the passed level stays open for review
    expect(app(UnlockService::class)->isLessonOpen($user, $course['lessons'][0][0]))->toBeTrue();
});
