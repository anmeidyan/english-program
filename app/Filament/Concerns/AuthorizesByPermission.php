<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Gates a Filament resource on one Spatie permission (PRD 2.3).
 * Set `protected static string $permission` on the resource.
 */
trait AuthorizesByPermission
{
    protected static function allowed(): bool
    {
        return (bool) auth()->user()?->can(static::$permission);
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }

    public static function canCreate(): bool
    {
        return static::allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed();
    }
}
