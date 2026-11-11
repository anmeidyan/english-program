<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LessonCompleted;
use App\Services\FlashcardService;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\StreakService;
use App\Services\Gamification\XpService;

class HandleLessonCompleted
{
    public function __construct(
        private readonly XpService $xp,
        private readonly StreakService $streak,
        private readonly FlashcardService $flashcards,
        private readonly BadgeService $badges,
    ) {}

    public function handle(LessonCompleted $event): void
    {
        $this->xp->awardConfigured($event->user, 'lesson_completed', $event->lesson->id);
        $this->streak->recordActivity($event->user);
        $this->flashcards->addLessonWords($event->user, $event->lesson);
        $this->badges->evaluate($event->user);
    }
}
