<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\ProgressStatus;
use App\Enums\SubmissionStatus;
use App\Models\ExamAttempt;
use App\Models\Level;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** PRD 7.1 Laporan: active learners, level funnel, exam stats, most-missed questions. */
class ReportService
{
    /** @return array<string, int|float> */
    public function overview(): array
    {
        return [
            'active_7d' => User::query()->where('last_active_date', '>=', now()->subDays(7)->toDateString())->count(),
            'learners' => User::role('student')->count(),
            'attempts_today' => ExamAttempt::query()->where('started_at', '>=', now()->startOfDay())->count(),
            'pending_reviews' => Submission::query()->where('status', '!=', SubmissionStatus::Reviewed->value)->count(),
        ];
    }

    /** Learners who reached / passed each level. @return Collection<int, array{level: string, reached: int, passed: int}> */
    public function funnel(): Collection
    {
        return Level::query()->orderBy('sort_order')->get()->map(fn (Level $level) => [
            'level' => $level->label(),
            'reached' => DB::table('user_progress')->where('progressable_type', 'level')->where('progressable_id', $level->id)->count(),
            'passed' => DB::table('user_progress')->where('progressable_type', 'level')->where('progressable_id', $level->id)
                ->whereIn('status', [ProgressStatus::Passed->value, ProgressStatus::PassedByPlacement->value])->count(),
        ]);
    }

    /** @return Collection<int, object> */
    public function examStats(int $limit = 25): Collection
    {
        return DB::table('exam_attempts as a')->join('exams as e', 'e.id', '=', 'a.exam_id')
            ->whereIn('a.status', [AttemptStatus::Passed->value, AttemptStatus::Failed->value])
            ->groupBy('e.id', 'e.title', 'e.type')
            ->selectRaw('e.title, e.type, count(*) as attempts, round(avg(a.score), 1) as avg_score, round(sum(case when a.passed = 0 then 1 else 0 end) / count(*) * 100, 1) as fail_rate')
            ->orderByDesc('attempts')->limit($limit)->get();
    }

    /** Questions answered wrong most often — candidates for review. @return Collection<int, object> */
    public function mostMissed(int $limit = 20): Collection
    {
        return DB::table('attempt_answers as aa')->join('questions as q', 'q.id', '=', 'aa.question_id')
            ->whereNotNull('aa.is_correct')
            ->groupBy('q.id', 'q.prompt', 'q.type')
            ->havingRaw('count(*) >= 3')
            ->selectRaw('q.id, q.prompt, q.type, count(*) as answered, round(sum(case when aa.is_correct = 0 then 1 else 0 end) / count(*) * 100, 1) as wrong_rate')
            ->orderByDesc('wrong_rate')->limit($limit)->get();
    }
}
