<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing the learner said, with the coach's correction and follow-up.
 *
 * @property int $id
 * @property int $speaking_session_id
 * @property string $transcript
 * @property string $corrected
 * @property int $score
 * @property list<array{original: string, corrected: string, explanation: string, type: string}> $corrections
 * @property string|null $tip
 * @property string $reply
 * @property string $coach
 */
class SpeakingTurn extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['corrections' => 'array', 'score' => 'integer'];
    }

    /** @return BelongsTo<SpeakingSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SpeakingSession::class, 'speaking_session_id');
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'transcript' => $this->transcript,
            'corrected' => $this->corrected,
            'score' => $this->score,
            'corrections' => $this->corrections,
            'tip' => $this->tip,
            'reply' => $this->reply,
        ];
    }
}
