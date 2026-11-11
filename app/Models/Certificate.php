<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $level_id
 * @property int $exam_attempt_id
 * @property string $code
 * @property array<string, float> $skill_scores
 * @property string $score
 * @property Carbon $issued_at
 * @property string|null $pdf_path
 * @property-read User $user
 * @property-read Level $level
 */
class Certificate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['skill_scores' => 'array', 'issued_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
