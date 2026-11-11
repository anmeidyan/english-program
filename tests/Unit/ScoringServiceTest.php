<?php

declare(strict_types=1);

use App\Enums\ExamType;
use App\Enums\GradedBy;
use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\Question;
use App\Services\Exam\ScoringService;

function scoredAnswer(Skill $skill, float $points, int $max = 1, ?bool $correct = true): AttemptAnswer
{
    $answer = new AttemptAnswer(['points_awarded' => $points, 'is_correct' => $correct, 'graded_by' => GradedBy::Auto]);
    $answer->setRelation('question', new Question(['skill' => $skill, 'type' => QuestionType::McqSingle, 'points' => $max]));

    return $answer;
}

it('computes total and per-skill scores from weighted points', function () {
    $answers = collect([
        scoredAnswer(Skill::Grammar, 1), scoredAnswer(Skill::Grammar, 0, 1, false),
        scoredAnswer(Skill::Listening, 2, 2),
    ]);
    $result = app(ScoringService::class)->compute($answers);

    expect($result['score'])->toBe(75.0)
        ->and($result['skill_scores'])->toBe(['grammar' => 50.0, 'listening' => 100.0])
        ->and($result['pending'])->toBe(0);
});

it('fails a unit exam with 80% total but listening 50% (minimum 60%)', function () {
    $exam = new Exam(['type' => ExamType::UnitExam, 'passing_score' => 75, 'min_skill_score' => 60]);
    $scoring = app(ScoringService::class);

    expect($scoring->passes($exam, 80.0, ['grammar' => 90.0, 'listening' => 50.0]))->toBeFalse()
        ->and($scoring->passes($exam, 80.0, ['grammar' => 90.0, 'listening' => 65.0]))->toBeTrue()
        ->and($scoring->weakSkills($exam, ['grammar' => 90.0, 'listening' => 50.0]))->toBe(['listening']);
});

it('counts ungraded speaking/writing as pending', function () {
    $answer = new AttemptAnswer(['points_awarded' => 0, 'is_correct' => null]);
    $answer->setRelation('question', new Question(['skill' => Skill::Speaking, 'type' => QuestionType::Speaking, 'points' => 2]));

    expect(app(ScoringService::class)->compute(collect([$answer]))['pending'])->toBe(1);
});
