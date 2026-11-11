<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LevelCode;
use App\Observers\CurriculumObserver;
use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property LevelCode $code
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property int $estimated_hours
 * @property bool $is_published
 */
#[ObservedBy(CurriculumObserver::class)]
class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['code' => LevelCode::class, 'is_published' => 'boolean', 'sort_order' => 'integer', 'estimated_hours' => 'integer'];
    }

    /** @return HasMany<Module, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    /** @return HasManyThrough<Unit, Module, $this> */
    public function units(): HasManyThrough
    {
        return $this->hasManyThrough(Unit::class, Module::class);
    }

    /** @return MorphOne<Exam, $this> */
    public function exam(): MorphOne
    {
        return $this->morphOne(Exam::class, 'examable');
    }

    /** @param Builder<Level> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function label(): string
    {
        return $this->code->label();
    }
}
