<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ProcessRecording;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\Exam\ExamService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Speaking answers (PRD 4.5, 11.4): stored outside public with a random name,
 * converted to mp3 by FFmpeg on the queue, served through signed URLs.
 */
class RecordingService
{
    public function __construct(private readonly ExamService $exams) {}

    public function storeForAttempt(ExamAttempt $attempt, Question $question, UploadedFile $file, ?string $transcript): string
    {
        $disk = config('englishpath.media.recordings_disk', 'local');
        $extension = match ($file->getMimeType()) {
            'audio/ogg', 'video/ogg' => 'ogg',
            'audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/aac' => 'm4a',
            'audio/mpeg' => 'mp3',
            default => 'webm',
        };
        $path = $file->storeAs("recordings/{$attempt->user_id}/{$attempt->id}", Str::random(40).'.'.$extension, $disk);
        if ($path === false) {
            throw new \RuntimeException('Recording upload failed.');
        }

        $this->exams->saveAnswer($attempt, $question, ['path' => $path, 'transcript' => $transcript]);
        if ($extension !== 'mp3') {
            ProcessRecording::dispatch($attempt->id, $question->id, $path);
        }

        return $path;
    }
}
