<?php

declare(strict_types=1);

use App\Enums\ExamType;
use App\Models\Exam;
use App\Services\Progress\CooldownService;

it('applies the PRD cooldown ladder', function (ExamType $type, array $rules, array $expected) {
    $exam = new Exam(['type' => $type, 'cooldown_rules' => $rules]);
    $service = app(CooldownService::class);

    foreach ($expected as $failCount => $minutes) {
        expect($service->minutesAfterFailure($exam, $failCount))->toBe($minutes);
    }
})->with([
    'lesson quiz: none' => [ExamType::LessonQuiz, [0], [1 => 0, 5 => 0]],
    'unit exam: 1h, 6h, 24h, 24h' => [ExamType::UnitExam, [60, 360, 1440], [1 => 60, 2 => 360, 3 => 1440, 7 => 1440]],
    'level exam: 24h then 72h' => [ExamType::LevelExam, [1440, 4320], [1 => 1440, 2 => 4320, 3 => 4320]],
]);
