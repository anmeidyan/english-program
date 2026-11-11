<?php

declare(strict_types=1);

namespace App\Services\Speech;

/** Default: the learner's browser transcribes with the Web Speech API and sends the text along. */
class BrowserSpeechToText implements SpeechToTextInterface
{
    public function transcribe(string $path): ?string
    {
        return null;
    }
}
