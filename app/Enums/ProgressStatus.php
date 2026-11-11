<?php

declare(strict_types=1);

namespace App\Enums;

/** PRD 5.4 state machine. */
enum ProgressStatus: string
{
    case Locked = 'locked';
    case Unlocked = 'unlocked';
    case InProgress = 'in_progress';
    case ExamReady = 'exam_ready';
    case ExamInProgress = 'exam_in_progress';
    case PendingReview = 'pending_review';
    case Failed = 'failed';
    case Passed = 'passed';
    case PassedByPlacement = 'passed_by_placement';

    public function isPassed(): bool
    {
        return $this === self::Passed || $this === self::PassedByPlacement;
    }

    public function isOpen(): bool
    {
        return $this !== self::Locked;
    }

    public function label(): string
    {
        return match ($this) {
            self::Locked => __('Terkunci'),
            self::Unlocked => __('Terbuka'),
            self::InProgress => __('Sedang dipelajari'),
            self::ExamReady => __('Siap ujian'),
            self::ExamInProgress => __('Ujian berjalan'),
            self::PendingReview => __('Menunggu penilaian'),
            self::Failed => __('Belum lulus'),
            self::Passed => __('Lulus'),
            self::PassedByPlacement => __('Lulus (placement)'),
        };
    }
}
