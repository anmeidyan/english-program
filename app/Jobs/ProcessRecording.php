<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AttemptAnswer;
use App\Models\Submission;
use App\Services\Speech\SpeechToTextInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/** Converts webm/ogg/mp4 recordings to mp3 (FFmpeg) and, with the whisper driver, transcribes them. */
class ProcessRecording implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $attemptId, public readonly int $questionId, public readonly string $path) {}

    public function handle(SpeechToTextInterface $speech): void
    {
        $disk = Storage::disk(config('englishpath.media.recordings_disk', 'local'));
        if (! $disk->exists($this->path)) {
            return;
        }
        $source = $disk->path($this->path);
        $targetPath = preg_replace('/\.[a-z0-9]+$/', '.mp3', $this->path) ?? $this->path.'.mp3';
        $target = $disk->path($targetPath);

        $result = Process::timeout(120)->run(['ffmpeg', '-y', '-loglevel', 'error', '-i', $source, '-vn', '-ac', '1', '-b:a', '64k', $target]);
        if (! $result->successful()) {
            Log::warning('ffmpeg conversion failed', ['path' => $this->path, 'error' => $result->errorOutput()]);

            return;
        }

        $answer = AttemptAnswer::query()->where('exam_attempt_id', $this->attemptId)->where('question_id', $this->questionId)->first();
        if ($answer === null) {
            return;
        }
        $data = is_array($answer->answer) ? $answer->answer : [];
        $data['path'] = $targetPath;
        if (empty($data['transcript'])) {
            $data['transcript'] = $speech->transcribe($targetPath);
        }
        $answer->update(['answer' => $data]);
        Submission::query()->where('attempt_answer_id', $answer->id)->update(['file_path' => $targetPath, 'transcript' => $data['transcript']]);
        $disk->delete($this->path);
    }
}
