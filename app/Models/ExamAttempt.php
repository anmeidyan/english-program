<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $exam_id
 * @property int $attempt_number
 * @property AttemptStatus $status
 * @property string|null $active_key
 * @property list<int> $question_ids
 * @property Carbon $started_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $submitted_at
 * @property string|null $score
 * @property array<string, float>|null $skill_scores
 * @property bool|null $passed
 * @property list<array<string, mixed>>|null $integrity_log
 * @property-read Exam $exam
 * @property-read User $user
 */
class ExamAttempt extends Model
{
    public const GRACE_SECONDS = 30;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'question_ids' => 'array',
            'skill_scores' => 'array',
            'integrity_log' => 'array',
            'passed' => 'boolean',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return HasMany<AttemptAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function isActive(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    /** Past the deadline including the grace window. */
    public function isPastGrace(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->expires_at !== null && $now->greaterThan($this->expires_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    public function secondsRemaining(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
