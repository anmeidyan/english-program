<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ExamType;
use App\Events\ExamPassed;
use App\Notifications\LevelExamResult;
use App\Services\CertificateService;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\XpService;
use App\Services\Progress\UnlockService;

/** ExamPassed → UnlockNextContent, AwardXp, IssueCertificate (PRD 8.2). */
class HandleExamPassed
{
    public function __construct(
        private readonly UnlockService $unlock,
        private readonly XpService $xp,
        private readonly CertificateService $certificates,
        private readonly BadgeService $badges,
    ) {}

    public function handle(ExamPassed $event): void
    {
        $attempt = $event->attempt;
        $user = $attempt->user;
        $exam = $attempt->exam;

        $this->unlock->unlockAfterPass($user, $exam->examable);

        $xpKey = match ($exam->type) {
            ExamType::LessonQuiz => 'quiz_passed',
            ExamType::UnitExam => 'unit_exam_passed',
            ExamType::LevelExam => 'level_exam_passed',
            ExamType::TensesCheckpoint => 'tenses_checkpoint_passed',
        };
        $this->xp->awardConfigured($user, $xpKey, $exam->id);

        if ($exam->type === ExamType::LevelExam) {
            $certificate = $this->certificates->issue($attempt);
            $user->notify(new LevelExamResult($attempt, $certificate));
        }

        $this->badges->evaluate($user);
    }
}
