<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var list<string> */
    private array $rolesBefore = [];

    protected function beforeSave(): void
    {
        /** @var User $user */
        $user = $this->record;
        $this->rolesBefore = $user->roles()->pluck('name')->sort()->values()->all();
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record;
        UserResource::auditRoleChange($user, $this->rolesBefore);
    }
}
