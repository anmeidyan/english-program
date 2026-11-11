<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionTag;
use App\Models\TenseTopic;
use App\Models\Unit;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates the default exam (PRD 5.1) for a lesson / unit / level / tense topic,
 * with question rules drawn from that item's tag. Counts are capped at what the pool holds.
 */
class ExamProvisioner
{
    /** @var array<string, int> */
    private const UNIT_RULES = ['grammar' => 5, 'vocabulary' => 6, 'reading' => 4, 'listening' => 4, 'speaking' => 3, 'writing' => 3];

    /** @var array<string, int> */
    private const LEVEL_RULES = ['grammar' => 12, 'vocabulary' => 14, 'reading' => 10, 'listening' => 10, 'speaking' => 6, 'writing' => 6];

    public function __construct(private readonly SettingsService $settings) {}

    public function forLesson(Lesson $lesson, string $tag): Exam
    {
        $exam = $this->upsert($lesson, ExamType::LessonQuiz, 'Quiz: '.$lesson->title);
        $this->replaceRules($exam, [[null, null, $tag, 8]]);

        return $this->syncCount($exam);
    }

    public function forUnit(Unit $unit, string $tag): Exam
    {
        $exam = $this->upsert($unit, ExamType::UnitExam, 'Unit Exam: '.$unit->title);
        $rules = [];
        foreach (self::UNIT_RULES as $skill => $count) {
            $rules[] = [$skill, null, $tag, $count];
        }
        $this->replaceRules($exam, $rules);

        return $this->syncCount($exam);
    }

    public function forLevel(Level $level, string $tag): Exam
    {
        $exam = $this->upsert($level, ExamType::LevelExam, 'Level Exam '.$level->label());
        $rules = [];
        foreach (self::LEVEL_RULES as $skill => $count) {
            $rules[] = [$skill, null, $tag, $count];
        }
        $rules[] = ['speaking', QuestionType::Speaking->value, $tag, 1];
        $rules[] = ['writing', QuestionType::Writing->value, $tag, 1];
        $this->replaceRules($exam, $rules);

        return $this->syncCount($exam);
    }

    public function forTense(TenseTopic $topic, string $tag): Exam
    {
        $exam = $this->upsert($topic, ExamType::TensesCheckpoint, 'Checkpoint: '.$topic->name);
        $this->replaceRules($exam, [[Skill::Grammar->value, null, $tag, 15]]);

        return $this->syncCount($exam);
    }

    private function upsert(Model $examable, ExamType $type, string $title): Exam
    {
        $defaults = $type->defaults();

        /** @var Exam $exam */
        $exam = Exam::query()->firstOrNew([
            'examable_type' => $examable->getMorphClass(),
            'examable_id' => $examable->getKey(),
        ]);

        if (! $exam->exists) {
            $exam->fill([
                'type' => $type,
                'title' => $title,
                'duration_minutes' => $defaults['duration_minutes'],
                'passing_score' => (int) $this->settings->get('passing_score.'.$type->value, $defaults['passing_score']),
                'min_skill_score' => (int) $this->settings->get('min_skill_score.'.$type->value, $defaults['min_skill_score']),
                'question_count' => $defaults['question_count'],
                'cooldown_rules' => $this->settings->get('cooldown.'.$type->value, $defaults['cooldown_rules']),
                'max_audio_plays' => (int) $this->settings->get('audio.max_plays_default', 2),
                'is_published' => true,
            ])->save();
        }

        return $exam;
    }

    /** @param list<array{0:string|null,1:string|null,2:string,3:int}> $rules */
    private function replaceRules(Exam $exam, array $rules): void
    {
        $exam->rules()->delete();

        foreach ($rules as [$skill, $type, $tagName, $count]) {
            $tag = QuestionTag::query()->firstOrCreate(['name' => $tagName]);
            $available = $this->available($tag->id, $skill, $type);
            $count = min($count, $available);
            if ($count <= 0) {
                continue;
            }
            $exam->rules()->create([
                'skill' => $skill,
                'question_type' => $type,
                'tag_id' => $tag->id,
                'count' => $count,
            ]);
        }
    }

    private function available(int $tagId, ?string $skill, ?string $type): int
    {
        return Question::query()->active()
            ->whereHas('tags', fn ($q) => $q->where('question_tags.id', $tagId))
            ->when($skill, fn ($q) => $q->where('skill', $skill))
            ->when($type, fn ($q) => $q->where('type', $type), fn ($q) => $q->whereNotIn('type', QuestionType::manualValues()))
            ->count();
    }

    private function syncCount(Exam $exam): Exam
    {
        $exam->update(['question_count' => (int) $exam->rules()->sum('count')]);

        return $exam;
    }
}
