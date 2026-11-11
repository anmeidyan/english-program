<?php

declare(strict_types=1);

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case Passed = 'passed';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Passed || $this === self::Failed;
    }
}
