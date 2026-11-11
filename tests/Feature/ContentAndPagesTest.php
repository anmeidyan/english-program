<?php

declare(strict_types=1);

use App\Filament\Resources\LessonResource;
use App\Livewire\Onboarding;
use App\Livewire\PlacementTest;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\TenseTopic;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->student = User::where('email', 'student@demo.test')->first();
});

it('seeds a complete A1 level: 2 modules, 6 units, 30 lessons, 300+ questions and exams', function () {
    expect(Module::count())->toBe(2)
        ->and(Unit::count())->toBe(6)
        ->and(Lesson::count())->toBe(30)
        ->and(Question::whereHas('tags', fn ($q) => $q->where('name', 'a1'))->count())->toBeGreaterThanOrEqual(300)
        ->and(Exam::where('type', 'lesson_quiz')->count())->toBe(30)
        ->and(Exam::where('type', 'unit_exam')->count())->toBe(6)
        ->and(Exam::where('type', 'level_exam')->where('question_count', '>', 0)->count())->toBe(1)
        ->and(TenseTopic::count())->toBe(16);
});

it('renders every learner page for the demo student', function () {
    $lesson = Lesson::query()->orderBy('id')->first();

    $this->actingAs($this->student)->get('/dashboard')->assertOk()->assertSee($lesson->title);
    $this->actingAs($this->student)->get('/learn/A1')->assertOk()->assertSee('Hello! Nice to Meet You');
    $this->actingAs($this->student)->get(route('lessons.show', $lesson))->assertOk()->assertSee('Verb');
    $this->actingAs($this->student)->get('/tenses')->assertOk()->assertSee('Simple Present');
    $this->actingAs($this->student)->get(route('tenses.show', 'simple-present'))->assertOk();
    $this->actingAs($this->student)->get('/flashcards')->assertOk();
    $this->actingAs($this->student)->get('/certificates')->assertOk();
    $this->actingAs($this->student)->get('/profile')->assertOk();
    $this->actingAs($this->student)->get('/placement')->assertOk();
    $this->actingAs($this->student)->get('/')->assertRedirect(route('dashboard'));
    auth()->logout();
    $this->get('/')->assertOk()->assertSee('EnglishPath');
    $this->get('/offline')->assertOk();
});

it('renders every seeded lesson and its practice without exposing answer keys', function () {
    $admin = User::where('email', 'admin@demo.test')->first();
    foreach (Lesson::all() as $lesson) {
        $html = $this->actingAs($admin)->get(route('lessons.show', ['lesson' => $lesson, 'preview' => 1]))->assertOk()->getContent();
        expect($html)->not->toContain('answer_key');
    }
});

it('serves offline bundles without answer keys', function () {
    $lesson = Lesson::query()->orderBy('id')->first();
    $json = $this->actingAs($this->student)->getJson(route('api.offline.lesson', $lesson))->assertOk()->assertJsonStructure(['lesson', 'questions', 'media', 'page_url'])->getContent();
    expect($json)->not->toContain('answer_key')->not->toContain('"correct"');
});

it('redirects new students to onboarding and onboarding to the dashboard', function () {
    $new = User::factory()->create(['onboarded_at' => null])->assignRole('student');
    $this->actingAs($new)->get('/dashboard')->assertRedirect(route('onboarding'));

    Livewire::actingAs($new)->test(Onboarding::class)
        ->set('goal', 'work')->call('next')
        ->set('dailyGoal', 30)->set('reminderTime', '20:00')->set('timezone', 'Asia/Jakarta')->call('next')
        ->call('finish', 'start')
        ->assertRedirect(route('dashboard'));

    expect($new->fresh()->onboarded_at)->not->toBeNull()
        ->and($new->fresh()->daily_goal_minutes)->toBe(30);
});

it('runs the placement test end to end without rendering answers', function () {
    $user = User::factory()->create(['onboarded_at' => now()])->assignRole('student');
    $component = Livewire::actingAs($user)->test(PlacementTest::class)->call('start');
    expect($component->html())->not->toContain('answer_key');

    for ($i = 0; $i < 40 && $component->get('resultLevel') === null; $i++) {
        $component->set('answer', 'a')->call('submit');
    }
    expect($component->get('resultLevel'))->not->toBeNull()
        ->and($user->placementResults()->count())->toBe(1);
});

it('lets admins open every Filament resource and blocks students', function () {
    $admin = User::where('email', 'admin@demo.test')->first();
    foreach (['levels', 'modules', 'units', 'lessons', 'questions', 'exams', 'vocabularies', 'submissions', 'users', 'audit-logs', 'tense-topics'] as $slug) {
        $this->actingAs($admin)->get("/admin/{$slug}")->assertOk();
    }
    $this->actingAs($admin)->get(LessonResource::getUrl('edit', ['record' => Lesson::first()]))->assertOk();
    $this->actingAs($admin)->get('/admin/questions/'.Question::first()->id.'/edit')->assertOk();
    $this->flushSession();
    $this->actingAs($this->student)->get('/admin/lessons')->assertForbidden();

    $this->flushSession();
    $reviewer = User::where('email', 'reviewer@demo.test')->first();
    $this->actingAs($reviewer)->get('/admin/submissions')->assertOk();
    $this->actingAs($reviewer)->get('/admin/lessons')->assertForbidden();
});
