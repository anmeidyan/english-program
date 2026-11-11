<?php

declare(strict_types=1);

namespace App\Services\Progress;

use Illuminate\Support\Carbon;

final readonly class AccessDecision
{
    public const LOCKED = 'locked';

    public const NOT_READY = 'not_ready';

    public const COOLDOWN = 'cooldown';

    public const REMEDIAL = 'remedial';

    public const PENDING_REVIEW = 'pending_review';

    public const ALREADY_PASSED = 'already_passed';

    public const UNPUBLISHED = 'unpublished';

    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?string $redirectUrl = null,
        public ?Carbon $until = null,
        public string $message = '',
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason, string $message, ?string $redirectUrl = null, ?Carbon $until = null): self
    {
        return new self(false, $reason, $redirectUrl, $until, $message);
    }
}
