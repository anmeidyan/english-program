<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Auth\Events\Registered;
use Spatie\Permission\Models\Role;

it('seeds four roles with the PRD permissions', function () {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['content_admin', 'reviewer', 'student', 'super_admin']);

    $admin = User::factory()->create()->assignRole(RoleName::SuperAdmin->value);
    foreach (PermissionName::cases() as $permission) {
        expect($admin->can($permission->value))->toBeTrue();
    }

    $reviewer = User::factory()->create()->assignRole(RoleName::Reviewer->value);
    expect($reviewer->can('submissions.review'))->toBeTrue()
        ->and($reviewer->can('lessons.manage'))->toBeFalse();
});

it('assigns the student role on registration', function () {
    $user = User::factory()->create();
    event(new Registered($user));

    expect($user->fresh()->hasRole('student'))->toBeTrue();
});

it('blocks students from the admin panel', function () {
    $student = User::factory()->create()->assignRole('student');

    $this->actingAs($student)->get('/admin')->assertForbidden();
});

it('lets staff roles into the admin panel', function (string $role) {
    $user = User::factory()->create()->assignRole($role);

    $this->actingAs($user)->get('/admin')->assertOk();
})->with(['reviewer', 'content_admin', 'super_admin']);

it('creates the demo accounts', function () {
    $this->seed(DemoUserSeeder::class);

    expect(User::where('email', 'student@demo.test')->first()->hasRole('student'))->toBeTrue()
        ->and(User::where('email', 'admin@demo.test')->first()->hasRole('super_admin'))->toBeTrue();
});
