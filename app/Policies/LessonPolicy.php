<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use App\Services\Progress\UnlockService;

class LessonPolicy
{
    public function __construct(private readonly UnlockService $unlock) {}

    public function view(User $user, Lesson $lesson): bool
    {
        return $this->unlock->lessonDecision($user, $lesson)->allowed;
    }
}
