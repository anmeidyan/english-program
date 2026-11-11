<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** PRD 13.1: admin actions (score changes, progress resets, role changes) are recorded. */
class AuditLogger
{
    /** @param array<string, mixed> $meta */
    public function log(?User $actor, string $action, ?Model $subject = null, array $meta = []): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
        ]);
    }
}
