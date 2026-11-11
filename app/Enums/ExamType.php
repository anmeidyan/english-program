<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamType: string
{
    case LessonQuiz = 'lesson_quiz';
    case UnitExam = 'unit_exam';
    case LevelExam = 'level_exam';
    case TensesCheckpoint = 'tenses_checkpoint';

    public function label(): string
    {
        return match ($this) {
            self::LessonQuiz => 'Lesson Quiz',
            self::UnitExam => 'Unit Exam',
            self::LevelExam => 'Level Exam',
            self::TensesCheckpoint => 'Tenses Checkpoint',
        };
    }

    /**
     * PRD 5.1 defaults: [question_count, duration_minutes|null, passing, min_skill, cooldown minutes].
     *
     * @return array{question_count:int, duration_minutes:int|null, passing_score:int, min_skill_score:int, cooldown_rules:list<int>}
     */
    public function defaults(): array
    {
        return match ($this) {
            self::LessonQuiz => ['question_count' => 8, 'duration_minutes' => null, 'passing_score' => 80, 'min_skill_score' => 0, 'cooldown_rules' => [0]],
            self::UnitExam => ['question_count' => 25, 'duration_minutes' => 30, 'passing_score' => 75, 'min_skill_score' => 60, 'cooldown_rules' => [60, 360, 1440]],
            self::LevelExam => ['question_count' => 60, 'duration_minutes' => 90, 'passing_score' => 75, 'min_skill_score' => 65, 'cooldown_rules' => [1440, 4320]],
            self::TensesCheckpoint => ['question_count' => 15, 'duration_minutes' => 15, 'passing_score' => 85, 'min_skill_score' => 0, 'cooldown_rules' => [0]],
        };
    }

    public function revealsAnswers(): bool
    {
        return $this === self::LessonQuiz || $this === self::TensesCheckpoint;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
