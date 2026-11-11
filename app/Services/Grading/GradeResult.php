<?php

declare(strict_types=1);

namespace App\Services\Grading;

/** Outcome of grading one answer. `isCorrect` null = needs manual review. */
final readonly class GradeResult
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public ?bool $isCorrect,
        public float $fraction,
        public array $details = [],
    ) {}

    /** @param array<string, mixed> $details */
    public static function correct(array $details = []): self
    {
        return new self(true, 1.0, $details);
    }

    /** @param array<string, mixed> $details */
    public static function wrong(array $details = []): self
    {
        return new self(false, 0.0, $details);
    }

    /** @param array<string, mixed> $details */
    public static function partial(float $fraction, array $details = []): self
    {
        $fraction = max(0.0, min(1.0, $fraction));

        return new self($fraction >= 1.0, $fraction, $details);
    }

    /** @param array<string, mixed> $details */
    public static function pending(array $details = []): self
    {
        return new self(null, 0.0, $details);
    }

    public function isPending(): bool
    {
        return $this->isCorrect === null;
    }
}
