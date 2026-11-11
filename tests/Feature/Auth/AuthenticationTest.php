<?php

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Livewire\Volt\Volt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    $this->assertGuest();
});

test('navigation menu can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response
        ->assertOk()
        ->assertSeeVolt('layout.navigation');
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('layout.navigation');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
});

test('login page lists the demo accounts and signs in with one click', function () {
    $this->seed(DemoUserSeeder::class);

    $this->get('/login')->assertOk()->assertSee('Akses demo')->assertSee('student@demo.test')->assertSee('admin@demo.test');

    Volt::test('pages.auth.login')->call('loginAs', 'student@demo.test')->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs(User::where('email', 'student@demo.test')->first());
});

test('one-click login only accepts the listed demo accounts and can be disabled', function () {
    $user = User::factory()->create(['email' => 'someone@example.com']);
    Volt::test('pages.auth.login')->call('loginAs', $user->email)->assertForbidden();

    config(['englishpath.demo_logins.enabled' => false]);
    $this->get('/login')->assertOk()->assertDontSee('Akses demo');
    Volt::test('pages.auth.login')->call('loginAs', 'student@demo.test')->assertForbidden();
});
