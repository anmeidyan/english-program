<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ProgressStatus;
use App\Models\User;
use App\Models\UserProgress;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class EloquentProgressRepository implements ProgressRepositoryInterface
{
    public function find(User $user, Model $item, bool $lock = false): ?UserProgress
    {
        return UserProgress::query()
            ->where('user_id', $user->id)
            ->where('progressable_type', $item->getMorphClass())
            ->where('progressable_id', $item->getKey())
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first();
    }

    public function status(User $user, Model $item): ProgressStatus
    {
        return $this->find($user, $item)->status ?? ProgressStatus::Locked;
    }

    public function put(User $user, Model $item, ProgressStatus $status, array $attributes = []): UserProgress
    {
        $keys = [
            'user_id' => $user->id,
            'progressable_type' => $item->getMorphClass(),
            'progressable_id' => $item->getKey(),
        ];

        try {
            return UserProgress::query()->updateOrCreate($keys, ['status' => $status] + $attributes);
        } catch (UniqueConstraintViolationException) {
            // concurrent first write: the row exists now
            return tap(UserProgress::query()->where($keys)->firstOrFail())->update(['status' => $status] + $attributes);
        }
    }

    public function forItems(User $user, string $morphType, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return UserProgress::query()
            ->where('user_id', $user->id)
            ->where('progressable_type', $morphType)
            ->whereIn('progressable_id', $ids)
            ->get()
            ->keyBy('progressable_id')
            ->all();
    }
}
