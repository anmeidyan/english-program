<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ExamAttempt;
use Illuminate\Foundation\Events\Dispatchable;

class ExamFailed
{
    use Dispatchable;

    public function __construct(public readonly ExamAttempt $attempt) {}
}
