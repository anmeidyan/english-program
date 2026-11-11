<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\ProgressStatus;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Database\Eloquent\Model;

interface ProgressRepositoryInterface
{
    public function find(User $user, Model $item, bool $lock = false): ?UserProgress;

    /** Missing row = locked (rows are created lazily, PRD 9.4). */
    public function status(User $user, Model $item): ProgressStatus;

    /** @param array<string, mixed> $attributes */
    public function put(User $user, Model $item, ProgressStatus $status, array $attributes = []): UserProgress;

    /**
     * @param  list<int>  $ids
     * @return array<int, UserProgress> keyed by progressable id
     */
    public function forItems(User $user, string $morphType, array $ids): array;
}
