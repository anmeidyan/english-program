<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One daily speaking conversation on a topic.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $practice_date
 * @property string $topic_key
 * @property string $level_code
 * @property int $turns_count
 * @property string|null $avg_score
 */
class SpeakingSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['practice_date' => 'date', 'turns_count' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SpeakingTurn, $this> */
    public function turns(): HasMany
    {
        return $this->hasMany(SpeakingTurn::class)->orderBy('id');
    }
}
