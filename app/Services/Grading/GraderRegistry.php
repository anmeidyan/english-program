<?php

declare(strict_types=1);

namespace App\Services\Grading;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Services\Grading\Graders\AcceptedSentenceGrader;
use App\Services\Grading\Graders\DictationGrader;
use App\Services\Grading\Graders\FillBlankGrader;
use App\Services\Grading\Graders\MatchingGrader;
use App\Services\Grading\Graders\McqMultiGrader;
use App\Services\Grading\Graders\McqSingleGrader;
use App\Services\Grading\Graders\SpeakingGrader;
use App\Services\Grading\Graders\TrueFalseNgGrader;
use App\Services\Grading\Graders\WordOrderGrader;
use App\Services\Grading\Graders\WritingGrader;

/** Strategy lookup: one grader per QuestionType (PRD 8.3). */
class GraderRegistry
{
    /** @var array<string, class-string<QuestionGraderInterface>> */
    private const MAP = [
        'mcq_single' => McqSingleGrader::class,
        'mcq_multi' => McqMultiGrader::class,
        'true_false_ng' => TrueFalseNgGrader::class,
        'fill_blank' => FillBlankGrader::class,
        'word_order' => WordOrderGrader::class,
        'matching' => MatchingGrader::class,
        'dictation' => DictationGrader::class,
        'error_correction' => AcceptedSentenceGrader::class,
        'transformation' => AcceptedSentenceGrader::class,
        'speaking' => SpeakingGrader::class,
        'writing' => WritingGrader::class,
    ];

    public function for(QuestionType $type): QuestionGraderInterface
    {
        return app(self::MAP[$type->value]);
    }

    public function grade(Question $question, mixed $answer): GradeResult
    {
        return $this->for($question->type)->grade($question, $answer);
    }

    public function correctAnswer(Question $question): mixed
    {
        return $this->for($question->type)->correctAnswer($question);
    }
}
