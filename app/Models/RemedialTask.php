<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RemedialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $exam_attempt_id
 * @property int $lesson_id
 * @property RemedialStatus $status
 * @property Carbon|null $completed_at
 * @property-read Lesson $lesson
 * @property-read ExamAttempt $attempt
 */
class RemedialTask extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => RemedialStatus::class, 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<ExamAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }
}
