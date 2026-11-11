<?php

declare(strict_types=1);

namespace App\Services\Speech;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Server-side transcription with the Whisper API (SPEECH_DRIVER=whisper). */
class WhisperSpeechToText implements SpeechToTextInterface
{
    public function transcribe(string $path): ?string
    {
        $key = config('englishpath.speech.whisper_api_key');
        if (! $key) {
            return null;
        }
        $disk = Storage::disk(config('englishpath.media.recordings_disk', 'local'));

        $response = Http::withToken((string) $key)->timeout(60)
            ->attach('file', $disk->get($path) ?? '', basename($path))
            ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => 'whisper-1', 'language' => 'en']);

        if (! $response->successful()) {
            Log::warning('Whisper transcription failed', ['status' => $response->status()]);

            return null;
        }

        return $response->json('text');
    }
}
