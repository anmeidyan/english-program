<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\AttemptStatus;
use App\Enums\ExamType;
use App\Enums\GradedBy;
use App\Enums\ProgressStatus;
use App\Enums\QuestionType;
use App\Enums\SubmissionStatus;
use App\Events\ExamFailed;
use App\Events\ExamPassed;
use App\Events\ExamSubmittedForReview;
use App\Exceptions\ExamException;
use App\Jobs\GenerateAiDraft;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\Grading\GraderRegistry;
use App\Services\Grading\QuestionPresenter;
use App\Services\Progress\CooldownService;
use App\Services\Progress\UnlockService;
use App\Services\SettingsService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Runs exam attempts (PRD 5.5): server-side timer, autosave, audio limits, integrity log,
 * grading entirely on the server, then the pass/fail/pending outcome.
 */
class ExamService
{
    private const MAX_INTEGRITY_EVENTS = 500;

    public function __construct(
        private readonly UnlockService $unlock,
        private readonly ProgressRepositoryInterface $progress,
        private readonly QuestionPicker $picker,
        private readonly GraderRegistry $graders,
        private readonly ScoringService $scoring,
        private readonly CooldownService $cooldown,
        private readonly RemedialService $remedial,
        private readonly QuestionPresenter $presenter,
        private readonly SettingsService $settings,
    ) {}

    // ------------------------------------------------------------------ start

    public function start(User $user, Exam $exam): ExamAttempt
    {
        if (($active = $this->activeAttempt($user, $exam)) !== null) {
            if (! $active->isPastGrace()) {
                return $active;
            }
            $this->finalize($active);
        }

        $decision = $this->unlock->examDecision($user, $exam);
        if (! $decision->allowed) {
            throw ExamException::denied($decision);
        }

        try {
            return DB::transaction(function () use ($user, $exam) {
                $item = $exam->examable;
                $row = $this->progress->find($user, $item, lock: true);

                $questionIds = $this->picker->pick($exam, $user);
                if ($questionIds === []) {
                    throw new ExamException(__('Bank soal untuk ujian ini belum tersedia.'), 409);
                }

                $attempt = ExamAttempt::query()->create([
                    'user_id' => $user->id,
                    'exam_id' => $exam->id,
                    'attempt_number' => ExamAttempt::query()->where('user_id', $user->id)->where('exam_id', $exam->id)->count() + 1,
                    'status' => AttemptStatus::InProgress,
                    'active_key' => $this->activeKey($user, $exam),
                    'question_ids' => $questionIds,
                    'started_at' => now(),
                    'expires_at' => $exam->duration_minutes ? now()->addMinutes($exam->duration_minutes) : null,
                    'integrity_log' => [],
                ]);

                $isRemedialRetake = $row !== null && $row->status->isPassed();
                if (! $isRemedialRetake) {
                    $this->progress->put($user, $item, ProgressStatus::ExamInProgress);
                }

                return $attempt;
            });
        } catch (UniqueConstraintViolationException) {
            // a parallel request created the active attempt first
            return $this->activeAttempt($user, $exam) ?? throw new ExamException(__('Gagal memulai ujian, coba lagi.'), 409);
        }
    }

    public function activeAttempt(User $user, Exam $exam): ?ExamAttempt
    {
        return ExamAttempt::query()->where('active_key', $this->activeKey($user, $exam))->first();
    }

    // ------------------------------------------------------------------ during the attempt

    public function saveAnswer(ExamAttempt $attempt, Question $question, mixed $answer): AttemptAnswer
    {
        $this->assertWritable($attempt, $question);

        return AttemptAnswer::query()->updateOrCreate(
            ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
            ['answer' => $answer],
        );
    }

    /**
     * Counts one audio play server-side and returns what to play.
     *
     * @return array{plays: int, max: int, tts_text: string|null, url: string|null}
     */
    public function recordAudioPlay(ExamAttempt $attempt, Question $question): array
    {
        $this->assertWritable($attempt, $question);
        $max = $attempt->exam->max_audio_plays;

        return DB::transaction(function () use ($attempt, $question, $max) {
            $answer = AttemptAnswer::query()->firstOrCreate(['exam_attempt_id' => $attempt->id, 'question_id' => $question->id]);
            $answer = AttemptAnswer::query()->lockForUpdate()->findOrFail($answer->id);
            if ($max > 0 && $answer->audio_plays >= $max) {
                throw new ExamException(__('Batas putar audio (:max kali) sudah tercapai.', ['max' => $max]), 429);
            }
            $answer->increment('audio_plays');
            $view = $this->presenter->present($question, QuestionPresenter::MODE_EXAM);

            return [
                'plays' => $answer->audio_plays,
                'max' => $max,
                'tts_text' => $question->media_path ? null : $this->presenter->audioScript($question),
                'url' => $view['media_url'],
            ];
        });
    }

    /** @param array<string, mixed> $meta */
    public function logEvent(ExamAttempt $attempt, string $type, array $meta = []): void
    {
        if (! $attempt->isActive()) {
            return;
        }
        DB::transaction(function () use ($attempt, $type, $meta) {
            $locked = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $log = $locked->integrity_log ?? [];
            if (count($log) >= self::MAX_INTEGRITY_EVENTS) {
                return;
            }
            $log[] = ['type' => $type, 'at' => now()->toIso8601String(), 'meta' => $meta];
            $locked->update(['integrity_log' => $log]);
        });
    }

    // ------------------------------------------------------------------ submit & grading

    /**
     * Learner submits. After expires_at + grace the submit is refused, but whatever was
     * autosaved is still graded (PRD 5.5).
     */
    public function submit(ExamAttempt $attempt): ExamAttempt
    {
        $late = $attempt->isPastGrace();
        $attempt = $this->finalize($attempt);

        if ($late) {
            throw new ExamException(__('Waktu ujian sudah habis. Jawaban yang tersimpan sudah dinilai.'), 422, null, route('exams.result', $attempt));
        }

        return $attempt;
    }

    public function finalize(ExamAttempt $attempt): ExamAttempt
    {
        $outcome = DB::transaction(function () use ($attempt) {
            /** @var ExamAttempt $locked */
            $locked = ExamAttempt::query()->with('exam.examable', 'user')->lockForUpdate()->findOrFail($attempt->id);
            if (! $locked->isActive()) {
                return [$locked, null];
            }

            $this->gradeAnswers($locked);
            $answers = $locked->answers()->with('question')->get();
            $result = $this->scoring->compute($answers);

            $locked->fill([
                'submitted_at' => now(),
                'active_key' => null,
                'score' => $result['score'],
                'skill_scores' => $result['skill_scores'],
            ]);

            if ($result['pending'] > 0) {
                $locked->fill(['status' => AttemptStatus::PendingReview, 'passed' => null])->save();
                $this->applyProgress($locked, null);

                return [$locked, ExamSubmittedForReview::class];
            }

            $passed = $this->scoring->passes($locked->exam, $result['score'], $result['skill_scores']);
            $locked->fill(['status' => $passed ? AttemptStatus::Passed : AttemptStatus::Failed, 'passed' => $passed])->save();
            $this->applyProgress($locked, $passed);

            return [$locked, $passed ? ExamPassed::class : ExamFailed::class];
        });

        [$final, $event] = $outcome;
        if ($event !== null) {
            event(new $event($final));
        }

        return $final;
    }

    /**
     * Re-scores an attempt after a reviewer graded its submissions; decides pass/fail once
     * nothing is pending any more.
     */
    public function rescoreAfterReview(ExamAttempt $attempt): ExamAttempt
    {
        $outcome = DB::transaction(function () use ($attempt) {
            /** @var ExamAttempt $locked */
            $locked = ExamAttempt::query()->with('exam.examable', 'user')->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status !== AttemptStatus::PendingReview) {
                return [$locked, null];
            }
            $result = $this->scoring->compute($locked->answers()->with('question')->get());
            $locked->fill(['score' => $result['score'], 'skill_scores' => $result['skill_scores']]);
            if ($result['pending'] > 0) {
                $locked->save();

                return [$locked, null];
            }

            $passed = $this->scoring->passes($locked->exam, $result['score'], $result['skill_scores']);
            $locked->fill(['status' => $passed ? AttemptStatus::Passed : AttemptStatus::Failed, 'passed' => $passed])->save();
            $this->applyProgress($locked, $passed);

            return [$locked, $passed ? ExamPassed::class : ExamFailed::class];
        });

        [$final, $event] = $outcome;
        if ($event !== null) {
            event(new $event($final));
        }

        return $final;
    }

    /** Finalises attempts whose timer ran out (scheduler). */
    public function finalizeExpired(): int
    {
        $count = 0;
        ExamAttempt::query()
            ->where('status', AttemptStatus::InProgress->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->subSeconds(ExamAttempt::GRACE_SECONDS))
            ->each(function (ExamAttempt $attempt) use (&$count) {
                $this->finalize($attempt);
                $count++;
            });

        return $count;
    }

    /** @return list<array<string, mixed>> client view of the attempt's questions, in attempt order */
    public function presentQuestions(ExamAttempt $attempt): array
    {
        $questions = Question::withTrashed()->whereIn('id', $attempt->question_ids)->get()->keyBy('id');
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $out = [];
        foreach ($attempt->question_ids as $id) {
            $question = $questions->get($id);
            if ($question === null) {
                continue;
            }
            $view = $this->presenter->present($question, QuestionPresenter::MODE_EXAM, $attempt->id);
            $saved = $answers->get($id);
            $view['saved_answer'] = $saved?->answer;
            $view['audio_plays'] = $saved->audio_plays ?? 0;
            $out[] = $view;
        }

        return $out;
    }

    // ------------------------------------------------------------------ internals

    private function gradeAnswers(ExamAttempt $attempt): void
    {
        $questions = Question::withTrashed()->whereIn('id', $attempt->question_ids)->get()->keyBy('id');
        $existing = $attempt->answers()->get()->keyBy('question_id');
        $aiEnabled = $this->settings->aiGraderEnabled();

        foreach ($attempt->question_ids as $id) {
            $question = $questions->get($id);
            if ($question === null) {
                continue;
            }
            /** @var AttemptAnswer $answer */
            $answer = $existing->get($id) ?? AttemptAnswer::query()->create(['exam_attempt_id' => $attempt->id, 'question_id' => $id]);

            if ($question->type->isManual()) {
                $this->queueForReview($attempt, $answer, $question, $aiEnabled);

                continue;
            }

            $grade = $this->graders->grade($question, $answer->answer);
            $answer->update([
                'is_correct' => $grade->isCorrect ?? false,
                'points_awarded' => round($grade->fraction * max(1, $question->points), 2),
                'graded_by' => GradedBy::Auto,
            ]);
        }
    }

    private function queueForReview(ExamAttempt $attempt, AttemptAnswer $answer, Question $question, bool $aiEnabled): void
    {
        $data = is_array($answer->answer) ? $answer->answer : [];
        $empty = $question->type === QuestionType::Speaking ? empty($data['path']) : trim((string) ($data['text'] ?? '')) === '';

        if ($empty) {
            // nothing to review: scored as 0
            $answer->update(['is_correct' => false, 'points_awarded' => 0, 'graded_by' => GradedBy::Auto]);

            return;
        }

        $answer->update(['is_correct' => null, 'points_awarded' => 0, 'graded_by' => null]);
        $submission = Submission::query()->firstOrCreate(['attempt_answer_id' => $answer->id], [
            'user_id' => $attempt->user_id,
            'type' => $question->type->value,
            'file_path' => $data['path'] ?? null,
            'text' => $data['text'] ?? null,
            'transcript' => $data['transcript'] ?? null,
            'status' => SubmissionStatus::Pending,
        ]);

        if ($aiEnabled) {
            GenerateAiDraft::dispatch($submission->id)->afterCommit();
        }
    }

    private function applyProgress(ExamAttempt $attempt, ?bool $passed): void
    {
        $user = $attempt->user;
        $exam = $attempt->exam;
        $item = $exam->examable;
        $row = $this->progress->find($user, $item, lock: true);
        $score = (float) $attempt->score;
        $best = max((float) ($row->best_score ?? 0), $score);
        $attempts = ($row->attempts_count ?? 0) + ($passed === null ? 0 : 1);

        // a remedial re-take of an already passed lesson never lowers its status
        if ($row !== null && $row->status->isPassed()) {
            $row->update(['best_score' => $best, 'attempts_count' => $attempts]);
            if ($passed && $item instanceof Lesson) {
                $this->remedial->completeFor($user, $item);
            }

            return;
        }

        if ($passed === null) {
            $this->progress->put($user, $item, ProgressStatus::PendingReview);

            return;
        }

        if ($passed) {
            $this->progress->put($user, $item, ProgressStatus::Passed, [
                'best_score' => $best,
                'attempts_count' => $attempts,
                'cooldown_until' => null,
                'completed_at' => now(),
            ]);
            if ($item instanceof Lesson) {
                $this->remedial->completeFor($user, $item);
            }

            return;
        }

        $fails = ($row->fail_count ?? 0) + 1;
        $this->progress->put($user, $item, ProgressStatus::Failed, [
            'best_score' => $best,
            'attempts_count' => $attempts,
            'fail_count' => $fails,
            'cooldown_until' => $this->cooldown->until($exam, $fails),
        ]);

        if (in_array($exam->type, [ExamType::UnitExam, ExamType::LevelExam], true)) {
            $this->remedial->createFor($attempt);
        }
    }

    private function assertWritable(ExamAttempt $attempt, Question $question): void
    {
        if (! $attempt->isActive()) {
            throw new ExamException(__('Ujian ini sudah selesai.'), 409);
        }
        if ($attempt->isPastGrace()) {
            throw new ExamException(__('Waktu ujian sudah habis.'), 422);
        }
        if (! in_array($question->id, array_map('intval', $attempt->question_ids), true)) {
            throw new ExamException(__('Soal tidak termasuk dalam ujian ini.'), 404);
        }
    }

    private function activeKey(User $user, Exam $exam): string
    {
        return $user->id.':'.$exam->id;
    }
}
