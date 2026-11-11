<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class AssignStudentRole
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if ($user instanceof User && $user->roles()->doesntExist()) {
            $user->assignRole(RoleName::Student->value);
        }
    }
}
