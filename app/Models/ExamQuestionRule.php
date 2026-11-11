<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionType;
use App\Enums\Skill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exam_id
 * @property Skill|null $skill
 * @property QuestionType|null $question_type
 * @property int|null $tag_id
 * @property int $difficulty_min
 * @property int $difficulty_max
 * @property int $count
 * @property-read QuestionTag|null $tag
 */
class ExamQuestionRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['skill' => Skill::class, 'question_type' => QuestionType::class, 'count' => 'integer'];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<QuestionTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(QuestionTag::class);
    }
}
