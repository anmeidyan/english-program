<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CurriculumObserver;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $level_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_published
 * @property-read Level $level
 */
#[ObservedBy(CurriculumObserver::class)]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('sort_order');
    }

    /** @param Builder<Module> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
