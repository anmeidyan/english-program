<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProgressStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $progressable_type
 * @property int $progressable_id
 * @property ProgressStatus $status
 * @property string|null $best_score
 * @property string|null $practice_best_score
 * @property int $attempts_count
 * @property int $fail_count
 * @property Carbon|null $cooldown_until
 * @property Carbon|null $completed_at
 */
class UserProgress extends Model
{
    protected $table = 'user_progress';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ProgressStatus::class,
            'cooldown_until' => 'datetime',
            'completed_at' => 'datetime',
            'attempts_count' => 'integer',
            'fail_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function progressable(): MorphTo
    {
        return $this->morphTo();
    }
}
