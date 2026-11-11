<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Content\ContentImportService;
use App\Services\Content\QuestionCsvImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportContent implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public readonly string $kind,
        public readonly string $path,
        public readonly bool $publish,
        public readonly string $reportKey,
        public readonly ?int $userId,
    ) {}

    public function handle(ContentImportService $curriculum, QuestionCsvImporter $questions, AuditLogger $audit): void
    {
        Cache::put($this->reportKey, ['status' => 'running'], now()->addDay());
        try {
            $raw = (string) Storage::disk('local')->get($this->path);
            $report = $this->kind === 'questions_csv'
                ? $questions->import($raw)
                : $curriculum->importLevel(json_decode($raw, true, 512, JSON_THROW_ON_ERROR), $this->publish);
            Cache::put($this->reportKey, ['status' => $report['errors'] === [] ? 'done' : 'done_with_errors'] + $report, now()->addDay());
            $audit->log($this->userId ? User::find($this->userId) : null, 'content.imported', null, ['kind' => $this->kind, 'stats' => $report['stats'], 'errors' => count($report['errors'])]);
        } catch (Throwable $e) {
            Cache::put($this->reportKey, ['status' => 'failed', 'errors' => [$e->getMessage()], 'stats' => []], now()->addDay());
        } finally {
            Storage::disk('local')->delete($this->path);
        }
    }
}
