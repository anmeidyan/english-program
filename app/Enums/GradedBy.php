<?php

declare(strict_types=1);

namespace App\Enums;

enum GradedBy: string
{
    case Auto = 'auto';
    case Ai = 'ai';
    case Reviewer = 'reviewer';
}
