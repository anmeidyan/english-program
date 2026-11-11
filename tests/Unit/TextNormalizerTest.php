<?php

declare(strict_types=1);

use App\Services\Grading\TextNormalizer;

it('normalises sentences for comparison', function () {
    expect(TextNormalizer::sameSentence('  I’m from   Bali! ', 'I am from Bali.'))->toBeTrue()
        ->and(TextNormalizer::sameSentence('She is a teacher', 'She is the teacher'))->toBeFalse();
});

it('measures word accuracy in order', function () {
    expect(TextNormalizer::wordAccuracy('one two three four', 'one two three four'))->toBe(1.0)
        ->and(TextNormalizer::wordAccuracy('one two three four', 'one three'))->toBe(0.5);
});
