<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $attempt_answer_id
 * @property int $user_id
 * @property string $type
 * @property string|null $file_path
 * @property string|null $text
 * @property string|null $transcript
 * @property array<string, mixed>|null $ai_score
 * @property array<string, int|float>|null $final_score
 * @property string|null $feedback
 * @property list<array{quote:string, comment:string}>|null $inline_feedback
 * @property int|null $reviewer_id
 * @property SubmissionStatus $status
 * @property Carbon|null $reviewed_at
 * @property-read AttemptAnswer $attemptAnswer
 * @property-read User $user
 * @property-read User|null $reviewer
 */
class Submission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'ai_score' => 'array',
            'final_score' => 'array',
            'inline_feedback' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AttemptAnswer, $this> */
    public function attemptAnswer(): BelongsTo
    {
        return $this->belongsTo(AttemptAnswer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
