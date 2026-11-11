<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $recommended_level_id
 * @property array<string, mixed> $score_detail
 * @property Carbon $taken_at
 * @property-read Level $recommendedLevel
 */
class PlacementResult extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['score_detail' => 'array', 'taken_at' => 'datetime'];
    }

    /** @return BelongsTo<Level, $this> */
    public function recommendedLevel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'recommended_level_id');
    }
}
