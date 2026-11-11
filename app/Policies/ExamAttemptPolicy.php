<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExamAttempt;
use App\Models\User;

class ExamAttemptPolicy
{
    public function view(User $user, ExamAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id || $user->can('submissions.review');
    }

    public function update(User $user, ExamAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
