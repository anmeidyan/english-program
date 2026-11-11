<?php

use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionTag;
use App\Models\Unit;
use App\Models\User;
use App\Services\Content\ExamProvisioner;
use App\Services\CurriculumService;
use App\Services\Exam\ExamService;
use App\Services\LessonService;
use App\Services\Progress\UnlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/** A verified, onboarded student. */
function student(): User
{
    return User::factory()->create(['onboarded_at' => now()])->assignRole('student');
}

/**
 * Builds a small published course: one level, one module, $units units × $lessons lessons.
 * Each lesson has 10 grammar MCQs (correct key "a"); each unit has a pool of 30 grammar and
 * 30 listening MCQs for its unit exam; the level has its own pool too.
 *
 * @return array{level: Level, units: list<Unit>, lessons: list<list<Lesson>>}
 */
function course(int $units = 2, int $lessons = 2, bool $withSpeakingInUnitExam = false): array
{
    $level = Level::factory()->create(['sort_order' => 1]);
    $module = Module::factory()->create(['level_id' => $level->id]);
    $levelTag = strtolower($level->code->value);
    $provisioner = app(ExamProvisioner::class);

    $out = ['level' => $level, 'units' => [], 'lessons' => []];
    for ($u = 1; $u <= $units; $u++) {
        $unit = Unit::factory()->create(['module_id' => $module->id, 'sort_order' => $u]);
        $out['units'][] = $unit;
        $row = [];
        for ($l = 1; $l <= $lessons; $l++) {
            $lesson = Lesson::factory()->create(['unit_id' => $unit->id, 'sort_order' => $l]);
            $qs = Question::factory()->count(10)->create(['level_id' => $level->id]);
            tagAll($qs, [$lesson->slug, $unit->slug]);
            $lesson->questions()->attach($qs->pluck('id')->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());
            $provisioner->forLesson($lesson, $lesson->slug);
            $row[] = $lesson;
        }
        foreach ([Skill::Grammar, Skill::Listening] as $skill) {
            tagAll(Question::factory()->count(30)->skill($skill)->create(['level_id' => $level->id]), [$unit->slug, $levelTag]);
        }
        if ($withSpeakingInUnitExam) {
            tagAll(Question::factory()->count(3)->speaking()->create(['level_id' => $level->id]), [$unit->slug]);
        }
        $exam = $provisioner->forUnit($unit, $unit->slug);
        if ($withSpeakingInUnitExam) {
            $exam->rules()->create(['skill' => 'speaking', 'question_type' => QuestionType::Speaking->value, 'tag_id' => QuestionTag::where('name', $unit->slug)->value('id'), 'count' => 1]);
            $exam->update(['question_count' => $exam->rules()->sum('count')]);
        }
        $out['lessons'][] = $row;
    }
    $provisioner->forLevel($level, $levelTag);
    app(CurriculumService::class)->flush();

    return $out;
}

function tagAll(iterable $questions, array $tags): void
{
    $ids = array_map(fn ($t) => QuestionTag::query()->firstOrCreate(['name' => $t])->id, $tags);
    foreach ($questions as $q) {
        $q->tags()->syncWithoutDetaching($ids);
    }
}

/** Answers every question of an attempt: $correct = fraction of questions answered correctly (per skill). */
function answerAttempt(ExamAttempt $attempt, float|array $correct = 1.0): void
{
    $questions = Question::query()->whereIn('id', $attempt->question_ids)->get()->groupBy(fn ($q) => $q->skill->value);
    foreach ($questions as $skill => $group) {
        $ratio = is_array($correct) ? ($correct[$skill] ?? 1.0) : $correct;
        $right = (int) round($group->count() * $ratio);
        foreach ($group->values() as $i => $q) {
            if ($q->type->isManual()) {
                continue;
            }
            app(ExamService::class)->saveAnswer($attempt, $q, $i < $right ? 'a' : 'b');
        }
    }
}

/** Opens + completes a lesson and passes its quiz. */
function passLesson(User $user, Lesson $lesson): ExamAttempt
{
    app(LessonService::class)->complete($user, $lesson);
    $exam = Exam::query()->where('examable_type', 'lesson')->where('examable_id', $lesson->id)->firstOrFail();
    $attempt = app(ExamService::class)->start($user, $exam);
    answerAttempt($attempt, 1.0);

    return app(ExamService::class)->submit($attempt);
}

function initCourse(User $user, Level $level): void
{
    app(UnlockService::class)->initialize($user, $level);
}
