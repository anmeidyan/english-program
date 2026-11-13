<?php

declare(strict_types=1);

use App\Services\Speaking\CoachResult;
use App\Services\Speaking\RuleBasedSpeakingCoach;
use App\Services\Speaking\SpeakingTopics;

function coachSays(string $text, array $history = []): CoachResult
{
    $result = (new RuleBasedSpeakingCoach)->respond(SpeakingTopics::find('introduce-yourself'), 'A1', $history, $text);
    assert($result !== null);

    return $result;
}

it('corrects common learner mistakes', function (string $said, string $expected) {
    expect(coachSays($said)->corrected)->toBe($expected);
})->with([
    'age with have' => ['I have 20 years old', 'I am 20 years old'],
    'age with have, third person' => ['My brother has twenty-five years old', 'My brother is twenty-five years old'],
    'third person -s' => ['My sister work in a bank and she like cats', 'My sister works in a bank and she likes cats'],
    'third person -es / -ies' => ['He go to school and she study English', 'He goes to school and she studies English'],
    'he/she don\'t' => ['She don\'t like coffee', 'She doesn\'t like coffee'],
    'did + past' => ['I didn\'t went to school yesterday', 'I didn\'t go to school yesterday'],
    'modal + to' => ['I can to speak English', 'I can speak English'],
    'to be agreement' => ['They is my friends and he are nice', 'They are my friends and he is nice'],
    'am + verb' => ['I am agree with you', 'I agree with you'],
    'very + verb' => ['I very like football', 'I really like football'],
    'uncountable' => ['She gave me many informations', 'She gave me a lot of information'],
    'uncountable plural' => ['Thanks for your advices', 'Thanks for your advice'],
    'a/an' => ['I eat a apple and an banana', 'I eat an apple and a banana'],
    'double comparative' => ['This one is more better', 'This one is better'],
    'lowercase i' => ['yes i am', 'yes I am'],
]);

it('does not touch correct sentences', function (string $said) {
    $result = coachSays($said);
    expect($result->corrected)->toBe($said)->and($result->corrections)->toBe([]);
})->with([
    'I am 20 years old and I live in Bandung.',
    'Does she like coffee? She can go now.',
    'I want to go to a university in Europe.',
    'He doesn\'t have a car, but he has an old bike.',
]);

it('explains each correction in Indonesian and lowers the score', function () {
    $good = coachSays('My name is Rina and I live in Jakarta with my family.');
    $bad = coachSays('My name is Rina and I have 20 years old and she work in Jakarta.');

    expect($bad->corrections)->toHaveCount(2)
        ->and($bad->corrections[0]['explanation'])->toContain('umur')
        ->and($bad->score)->toBeLessThan($good->score)
        ->and($good->score)->toBe(100);
});

it('keeps the conversation going with the topic follow-ups', function () {
    $topic = SpeakingTopics::find('introduce-yourself');
    expect(coachSays('I am Budi.')->reply)->toContain($topic['followups'][0])
        ->and(coachSays('I am Budi.', [['learner' => 'x', 'coach' => 'y']])->reply)->toContain($topic['followups'][1]);
});

it('caps the score of very short answers', function () {
    expect(coachSays('Budi.')->score)->toBeLessThanOrEqual(60);
});
