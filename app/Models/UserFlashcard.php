<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $vocabulary_id
 * @property float $ease_factor
 * @property int $interval_days
 * @property int $repetitions
 * @property Carbon $due_at
 * @property Carbon|null $last_reviewed_at
 * @property-read Vocabulary $vocabulary
 */
class UserFlashcard extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ease_factor' => 'float',
            'interval_days' => 'integer',
            'repetitions' => 'integer',
            'due_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vocabulary, $this> */
    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
