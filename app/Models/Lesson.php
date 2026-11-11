<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LessonType;
use App\Enums\Skill;
use App\Observers\CurriculumObserver;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $unit_id
 * @property string $title
 * @property string $slug
 * @property LessonType $type
 * @property Skill $skill
 * @property list<array{type:string, data:array<string,mixed>}>|null $content
 * @property int $estimated_minutes
 * @property int $sort_order
 * @property bool $is_published
 * @property-read Unit $unit
 */
#[ObservedBy(CurriculumObserver::class)]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => LessonType::class,
            'skill' => Skill::class,
            'content' => 'array',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'estimated_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** @return BelongsToMany<Vocabulary, $this> */
    public function vocabularies(): BelongsToMany
    {
        return $this->belongsToMany(Vocabulary::class);
    }

    /** @return MorphOne<Exam, $this> */
    public function exam(): MorphOne
    {
        return $this->morphOne(Exam::class, 'examable');
    }

    /** @param Builder<Lesson> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
