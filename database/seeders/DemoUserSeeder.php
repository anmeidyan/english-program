<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Demo Student', 'student@demo.test', RoleName::Student],
            ['Demo Admin', 'admin@demo.test', RoleName::SuperAdmin],
            ['Demo Content Admin', 'content@demo.test', RoleName::ContentAdmin],
            ['Demo Reviewer', 'reviewer@demo.test', RoleName::Reviewer],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'email_verified_at' => now(), 'onboarded_at' => now()],
            );
            $user->syncRoles([$role->value]);
        }
    }
}
