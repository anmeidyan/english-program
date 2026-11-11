<?php

use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\FlashcardController;
use App\Http\Controllers\Api\LessonProgressController;
use App\Http\Controllers\Api\OfflineBundleController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Learner\CertificateController;
use App\Http\Controllers\Learner\DashboardController;
use App\Http\Controllers\Learner\ExamController;
use App\Http\Controllers\Learner\FlashcardPageController;
use App\Http\Controllers\Learner\LearningPathController;
use App\Http\Controllers\Learner\LessonController;
use App\Http\Controllers\Learner\LocaleController;
use App\Http\Controllers\Learner\RecordingController;
use App\Http\Controllers\Learner\TensesController;
use App\Livewire\Onboarding;
use App\Livewire\PlacementTest;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');
Route::view('/offline', 'offline')->name('offline');
Route::get('/verify/{code}', [CertificateController::class, 'verify'])->name('certificates.verify');
Route::get('/locale/{locale}', LocaleController::class)->name('locale');

Route::middleware('auth')->group(function () {
    Route::get('/onboarding', Onboarding::class)->middleware('verified')->name('onboarding');
    Route::get('/placement', PlacementTest::class)->middleware('verified')->name('placement');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/learn', [LearningPathController::class, 'index'])->name('learn');
    Route::get('/learn/lessons/{lesson:slug}', [LessonController::class, 'show'])->middleware('unlocked')->name('lessons.show');
    Route::get('/learn/{level:code}', [LearningPathController::class, 'show'])->name('learn.level');

    Route::get('/exams/{exam}', [ExamController::class, 'show'])->middleware('unlocked')->name('exams.show');
    Route::get('/exams/attempts/{attempt}', [ExamController::class, 'run'])->middleware('owner')->name('exams.run');
    Route::get('/exams/attempts/{attempt}/result', [ExamController::class, 'result'])->middleware('owner')->name('exams.result');

    Route::get('/tenses', [TensesController::class, 'index'])->name('tenses.index');
    Route::get('/tenses/{topic:slug}', [TensesController::class, 'show'])->name('tenses.show');
    Route::get('/flashcards', FlashcardPageController::class)->name('flashcards');
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/{certificate:code}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::get('/recordings/{submission}', RecordingController::class)->middleware('signed')->name('recordings.show');
    Route::view('/profile', 'profile')->name('profile');

    // JSON endpoints: same origin, session auth + CSRF (PRD 10)
    Route::prefix('app/api')->name('api.')->middleware('throttle:api')->group(function () {
        Route::post('/lessons/{lesson}/complete', [LessonProgressController::class, 'complete'])->middleware('unlocked')->name('lessons.complete');
        Route::post('/lessons/{lesson}/practice', [LessonProgressController::class, 'practice'])->middleware('unlocked')->name('lessons.practice');

        Route::post('/exams/{exam}/attempts', [AttemptController::class, 'start'])->middleware(['verified', 'unlocked', 'throttle:exam-submit'])->name('attempts.start');
        Route::middleware('owner')->group(function () {
            Route::get('/attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
            Route::patch('/attempts/{attempt}/answers/{question}', [AttemptController::class, 'saveAnswer'])->middleware('throttle:autosave')->name('attempts.answer');
            Route::post('/attempts/{attempt}/audio-play/{question}', [AttemptController::class, 'audioPlay'])->name('attempts.audio');
            Route::post('/attempts/{attempt}/recordings/{question}', [AttemptController::class, 'uploadRecording'])->name('attempts.recording');
            Route::post('/attempts/{attempt}/events', [AttemptController::class, 'logEvent'])->name('attempts.events');
            Route::post('/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->middleware('throttle:exam-submit')->name('attempts.submit');
        });

        Route::get('/flashcards/due', [FlashcardController::class, 'due'])->name('flashcards.due');
        Route::post('/flashcards/{card}/review', [FlashcardController::class, 'review'])->name('flashcards.review');
        Route::post('/flashcards/words/{vocabulary}', [FlashcardController::class, 'add'])->name('flashcards.add');
        Route::get('/glossary', [FlashcardController::class, 'lookup'])->name('glossary');

        Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
        Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

        Route::get('/offline/lessons/{lesson}/bundle', [OfflineBundleController::class, 'lesson'])->middleware('unlocked')->name('offline.lesson');
        Route::get('/offline/units/{unit}/bundle', [OfflineBundleController::class, 'unit'])->name('offline.unit');
    });
});

require __DIR__.'/auth.php';
