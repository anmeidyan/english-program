<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Exam\ExamService;
use Illuminate\Console\Command;

class FinalizeExpiredAttempts extends Command
{
    protected $signature = 'exams:finalize-expired';

    protected $description = 'Grade exam attempts whose timer (plus grace) has run out';

    public function handle(ExamService $exams): int
    {
        $count = $exams->finalizeExpired();
        $this->info("Finalised {$count} attempt(s).");

        return self::SUCCESS;
    }
}
