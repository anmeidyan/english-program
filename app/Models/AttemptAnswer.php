<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GradedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $exam_attempt_id
 * @property int $question_id
 * @property mixed $answer
 * @property bool|null $is_correct
 * @property string $points_awarded
 * @property int $audio_plays
 * @property GradedBy|null $graded_by
 * @property-read ExamAttempt $attempt
 * @property-read Question $question
 * @property-read Submission|null $submission
 */
class AttemptAnswer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['answer' => 'array', 'is_correct' => 'boolean', 'graded_by' => GradedBy::class, 'audio_plays' => 'integer'];
    }

    /** @return BelongsTo<ExamAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    /** @return HasOne<Submission, $this> */
    public function submission(): HasOne
    {
        return $this->hasOne(Submission::class);
    }
}
