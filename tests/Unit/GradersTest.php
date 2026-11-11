<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\Question;
use App\Services\Grading\GraderRegistry;

function q(string $type, array $payload, array $key, int $points = 1): Question
{
    return new Question(['type' => QuestionType::from($type), 'skill' => Skill::Grammar, 'prompt' => 'p', 'payload' => $payload, 'answer_key' => $key, 'points' => $points]);
}

beforeEach(fn () => $this->graders = app(GraderRegistry::class));

it('grades mcq_single', function () {
    $q = q('mcq_single', [], ['correct' => 'b']);
    expect($this->graders->grade($q, 'b')->isCorrect)->toBeTrue()
        ->and($this->graders->grade($q, 'a')->isCorrect)->toBeFalse()
        ->and($this->graders->grade($q, null)->isCorrect)->toBeFalse();
});

it('grades mcq_multi with optional partial credit', function () {
    $strict = q('mcq_multi', [], ['correct' => ['a', 'c']]);
    $partial = q('mcq_multi', [], ['correct' => ['a', 'c'], 'partial' => true]);

    expect($this->graders->grade($strict, ['c', 'a'])->isCorrect)->toBeTrue()
        ->and($this->graders->grade($strict, ['a'])->fraction)->toBe(0.0)
        ->and($this->graders->grade($partial, ['a'])->fraction)->toBe(0.5)
        ->and($this->graders->grade($partial, ['a', 'b'])->fraction)->toBe(0.0);
});

it('grades true/false/not given', function () {
    $q = q('true_false_ng', [], ['correct' => 'not_given']);
    expect($this->graders->grade($q, 'not_given')->isCorrect)->toBeTrue()
        ->and($this->graders->grade($q, 'false')->isCorrect)->toBeFalse();
});

it('grades fill_blank case-insensitively with trimming and several accepted answers', function () {
    $q = q('fill_blank', ['blanks' => 2], ['blanks' => [['are'], ['door', 'window']]]);
    expect($this->graders->grade($q, ['  ARE ', 'Window'])->isCorrect)->toBeTrue()
        ->and($this->graders->grade($q, ['are', 'table'])->fraction)->toBe(0.5)
        ->and($this->graders->grade($q, 'are')->fraction)->toBe(0.5);
});

it('grades word_order ignoring case and punctuation', function () {
    $q = q('word_order', ['tokens' => ['is', 'She', 'teacher', 'a']], ['accepted' => ['She is a teacher.']]);
    expect($this->graders->grade($q, ['She', 'is', 'a', 'teacher'])->isCorrect)->toBeTrue()
        ->and($this->graders->grade($q, ['is', 'She', 'a', 'teacher'])->isCorrect)->toBeFalse();
});

it('grades matching per pair', function () {
    $q = q('matching', [], ['pairs' => ['l1' => 'r1', 'l2' => 'r2', 'l3' => 'r3', 'l4' => 'r4']]);
    expect($this->graders->grade($q, ['l1' => 'r1', 'l2' => 'r2', 'l3' => 'r3', 'l4' => 'r4'])->isCorrect)->toBeTrue()
        ->and($this->graders->grade($q, ['l1' => 'r1', 'l2' => 'r3'])->fraction)->toBe(0.25);
});

it('tolerates one typo in dictation words longer than 5 letters only', function () {
    $q = q('dictation', [], ['text' => 'My grandmother is eighty years old.']);
    expect($this->graders->grade($q, 'my grandmuther is eighty years old')->isCorrect)->toBeTrue()
        ->and($this->graders->grade($q, 'My grandmother is eigty years old.')->isCorrect)->toBeTrue()
        ->and($this->graders->grade($q, 'My grandmother is eighty yeers old.')->isCorrect)->toBeFalse()
        ->and($this->graders->grade($q, 'My grandmother is eighty years old.')->isCorrect)->toBeTrue();
});

it('grades error correction and transformation against accepted sentences (contractions equal)', function () {
    $q = q('transformation', [], ['accepted' => ['They are not doctors.']]);
    expect($this->graders->grade($q, "They aren't doctors")->isCorrect)->toBeTrue()
        ->and($this->graders->grade($q, 'They is not doctors.')->isCorrect)->toBeFalse();

    $e = q('error_correction', [], ['accepted' => ['He goes to school every day.']]);
    expect($this->graders->grade($e, 'he goes to school every day')->isCorrect)->toBeTrue();
});

it('scores speaking stage 1 by word accuracy and leaves it for review', function () {
    $q = q('speaking', ['target_text' => 'Hello my name is Rina'], []);
    $result = $this->graders->grade($q, ['transcript' => 'hello my name is Rita']);
    expect($result->isPending())->toBeTrue()
        ->and($result->details['accuracy'])->toBe(0.8);
});

it('never auto-grades writing', function () {
    expect($this->graders->grade(q('writing', [], []), ['text' => 'My family is big.'])->isPending())->toBeTrue();
});
