<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\LessonCompleted;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Progress\UnlockService;

class LessonService
{
    public function __construct(private readonly UnlockService $unlock) {}

    public function open(User $user, Lesson $lesson): void
    {
        $this->unlock->markLessonOpened($user, $lesson);
    }

    /** Learner finished the lesson content: quiz unlocks, XP + flashcards + streak follow. */
    public function complete(User $user, Lesson $lesson): bool
    {
        $first = $this->unlock->markLessonCompleted($user, $lesson);
        event(new LessonCompleted($user, $lesson));

        return $first;
    }
}
