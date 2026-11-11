<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Submission;

interface AiGraderInterface
{
    /**
     * Draft rubric score + feedback for a reviewer to confirm or correct.
     *
     * @return array{scores: array<string, int>, feedback: string, model?: string}|null null = no draft
     */
    public function draft(Submission $submission): ?array;
}
