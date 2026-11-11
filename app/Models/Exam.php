<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $examable_type
 * @property int $examable_id
 * @property ExamType $type
 * @property string $title
 * @property int|null $duration_minutes
 * @property int $passing_score
 * @property int $min_skill_score
 * @property int $question_count
 * @property list<int>|null $cooldown_rules
 * @property int $max_audio_plays
 * @property bool $is_published
 * @property-read Lesson|Unit|Level|TenseTopic $examable
 */
class Exam extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'cooldown_rules' => 'array',
            'is_published' => 'boolean',
            'passing_score' => 'integer',
            'min_skill_score' => 'integer',
            'question_count' => 'integer',
            'duration_minutes' => 'integer',
            'max_audio_plays' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function examable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<ExamQuestionRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(ExamQuestionRule::class);
    }

    /** @return HasMany<ExamAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
