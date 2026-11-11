<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Submission;

/** Default driver (PRD 15.3): no AI, reviewers score everything manually. */
class NullAiGrader implements AiGraderInterface
{
    public function draft(Submission $submission): ?array
    {
        return null;
    }
}
