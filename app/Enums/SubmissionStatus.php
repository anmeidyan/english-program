<?php

declare(strict_types=1);

namespace App\Enums;

enum SubmissionStatus: string
{
    case Pending = 'pending_review';
    case AiDraft = 'ai_draft';
    case Reviewed = 'reviewed';
}
