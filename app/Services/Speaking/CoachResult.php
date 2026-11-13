<?php

declare(strict_types=1);

namespace App\Services\Speaking;

/** The coach's answer to one learner turn. */
final class CoachResult
{
    /**
     * @param  list<array{original: string, corrected: string, explanation: string, type: string}>  $corrections
     */
    public function __construct(
        public readonly string $corrected,
        public readonly int $score,
        public readonly array $corrections,
        public readonly ?string $tip,
        public readonly string $reply,
        public readonly string $coach,
    ) {}
}
