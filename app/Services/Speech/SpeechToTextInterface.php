<?php

declare(strict_types=1);

namespace App\Services\Speech;

interface SpeechToTextInterface
{
    /** Transcript of a stored recording, or null when transcription happens elsewhere. */
    public function transcribe(string $path): ?string;
}
