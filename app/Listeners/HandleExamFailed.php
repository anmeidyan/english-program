<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ExamType;
use App\Events\ExamFailed;
use App\Models\UserProgress;
use App\Notifications\CooldownEnded;
use App\Notifications\LevelExamResult;

/** Schedules the "cooldown finished" push and mails level-exam results. */
class HandleExamFailed
{
    public function handle(ExamFailed $event): void
    {
        $attempt = $event->attempt;
        $user = $attempt->user;
        $exam = $attempt->exam;

        $row = UserProgress::query()->where('user_id', $user->id)
            ->where('progressable_type', $exam->examable_type)->where('progressable_id', $exam->examable_id)->first();

        if ($row?->cooldown_until !== null && $row->cooldown_until->isFuture()) {
            $user->notify((new CooldownEnded($exam))->delay($row->cooldown_until));
        }

        if ($exam->type === ExamType::LevelExam) {
            $user->notify(new LevelExamResult($attempt, null));
        }
    }
}
