<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VocabularyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $word
 * @property string|null $part_of_speech
 * @property string|null $ipa_us
 * @property string|null $ipa_uk
 * @property string|null $audio_us
 * @property string|null $audio_uk
 * @property string $meaning_id
 * @property string|null $definition_en
 * @property string|null $example
 * @property string|null $image_path
 * @property int|null $level_id
 */
class Vocabulary extends Model
{
    /** @use HasFactory<VocabularyFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return BelongsToMany<Lesson, $this> */
    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class);
    }
}
