<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;
use App\Services\Progress\UnlockService;

class ExamPolicy
{
    public function __construct(private readonly UnlockService $unlock) {}

    public function take(User $user, Exam $exam): bool
    {
        return $user->can('exams.take') && $this->unlock->examDecision($user, $exam)->allowed;
    }
}
