<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CurriculumObserver;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $module_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_published
 * @property-read Module $module
 */
#[ObservedBy(CurriculumObserver::class)]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    /** @return MorphOne<Exam, $this> */
    public function exam(): MorphOne
    {
        return $this->morphOne(Exam::class, 'examable');
    }

    /** @param Builder<Unit> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
