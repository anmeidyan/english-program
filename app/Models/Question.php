<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionType;
use App\Enums\Skill;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * answer_key and explanation are hidden from every serialization (PRD 13.1).
 *
 * @property int $id
 * @property QuestionType $type
 * @property Skill $skill
 * @property int|null $level_id
 * @property int $difficulty
 * @property string $prompt
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $answer_key
 * @property string|null $explanation
 * @property int $points
 * @property string|null $media_path
 * @property bool $is_active
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['answer_key', 'explanation'];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'skill' => Skill::class,
            'payload' => 'array',
            'answer_key' => 'array',
            'is_active' => 'boolean',
            'points' => 'integer',
            'difficulty' => 'integer',
        ];
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return BelongsToMany<QuestionTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(QuestionTag::class, 'question_tag', 'question_id', 'tag_id');
    }

    /** @return BelongsToMany<Lesson, $this> */
    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class);
    }

    /** @param Builder<Question> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
