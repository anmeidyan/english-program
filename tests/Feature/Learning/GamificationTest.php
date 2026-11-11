<?php

declare(strict_types=1);

use App\Models\Badge;
use App\Services\Gamification\StreakService;
use App\Services\LessonService;
use Database\Seeders\BadgeSeeder;

it('awards XP once per source', function () {
    $course = course(units: 1, lessons: 1);
    $user = student();
    initCourse($user, $course['level']);
    $lesson = $course['lessons'][0][0];

    passLesson($user, $lesson);
    expect($user->fresh()->xp_total)->toBe(10 + 20);

    app(LessonService::class)->complete($user, $lesson);
    expect($user->fresh()->xp_total)->toBe(30);
});

it('grows the streak on consecutive days, uses one freeze per week, and resets after a gap', function () {
    $user = student();
    $user->update(['timezone' => 'Asia/Jakarta']);
    $streak = app(StreakService::class);

    $this->travelTo(now()->startOfWeek()->addHours(10));
    $streak->recordActivity($user);
    $this->travel(1)->day();
    $streak->recordActivity($user);
    expect($user->fresh()->streak_current)->toBe(2);

    $this->travel(2)->days(); // missed one day → freeze
    $streak->recordActivity($user);
    expect($user->fresh()->streak_current)->toBe(3);

    $this->travel(2)->days(); // second miss in the same week → reset
    $streak->recordActivity($user);
    expect($user->fresh()->streak_current)->toBe(1)
        ->and($user->fresh()->streak_longest)->toBe(3);
});

it('awards the First Lesson badge', function () {
    $this->seed(BadgeSeeder::class);
    $course = course(units: 1, lessons: 1);
    $user = student();
    initCourse($user, $course['level']);
    passLesson($user, $course['lessons'][0][0]);

    expect($user->badges()->pluck('badge_id')->all())->toContain(Badge::where('code', 'first-lesson')->value('id'));
});
