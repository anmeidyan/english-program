<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $time
 * @property string $aspect
 * @property array<string, string> $formula
 * @property array<string, mixed>|null $content
 * @property int|null $required_level_id
 * @property int $sort_order
 * @property bool $is_published
 * @property-read Level|null $requiredLevel
 */
class TenseTopic extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['formula' => 'array', 'content' => 'array', 'is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Level, $this> */
    public function requiredLevel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'required_level_id');
    }

    /** @return MorphOne<Exam, $this> */
    public function exam(): MorphOne
    {
        return $this->morphOne(Exam::class, 'examable');
    }

    /** @param Builder<TenseTopic> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
