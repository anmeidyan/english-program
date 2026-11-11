<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $locale
 * @property string $timezone
 * @property int $daily_goal_minutes
 * @property int|null $current_level_id
 * @property int $xp_total
 * @property int $streak_current
 * @property int $streak_longest
 * @property Carbon|null $last_active_date
 * @property Carbon|null $streak_freeze_used_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $onboarded_at
 */
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'timezone',
        'daily_goal_minutes',
        'reminder_time',
        'learning_goal',
        'current_level_id',
        'xp_total',
        'streak_current',
        'streak_longest',
        'last_active_date',
        'streak_freeze_used_at',
        'onboarded_at',
        'email_verified_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_active_date' => 'date',
            'streak_freeze_used_at' => 'date',
            'onboarded_at' => 'datetime',
            'xp_total' => 'integer',
            'streak_current' => 'integer',
            'streak_longest' => 'integer',
            'daily_goal_minutes' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(RoleName::staff());
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(RoleName::staff());
    }
}
