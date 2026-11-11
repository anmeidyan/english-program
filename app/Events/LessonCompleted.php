<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class LessonCompleted
{
    use Dispatchable;

    public function __construct(public readonly User $user, public readonly Lesson $lesson) {}
}
