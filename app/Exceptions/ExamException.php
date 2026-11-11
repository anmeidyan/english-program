<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Progress\AccessDecision;
use RuntimeException;

class ExamException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly ?AccessDecision $decision = null,
        public readonly ?string $redirectUrl = null,
    ) {
        parent::__construct($message);
    }

    public static function denied(AccessDecision $decision): self
    {
        return new self($decision->message, 403, $decision, $decision->redirectUrl);
    }
}
