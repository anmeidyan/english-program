<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use App\Services\Content\Generators\TemplateQuestionGenerator;
use App\Services\Exam\ExamService;
use App\Services\Exam\QuestionPicker;
use App\Services\Grading\GraderRegistry;
use App\Services\Grading\QuestionPresenter;
use App\Services\LessonService;
use App\Services\Progress\UnlockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

it('shows the correct option plus a random subset of distractors, in random order', function () {
    $q = Question::factory()->create([
        'payload' => ['options' => array_map(fn ($i) => ['key' => chr(97 + $i), 'text' => "opt{$i}"], range(0, 5)), 'max_options' => 4],
        'answer_key' => ['correct' => 'd'],
    ]);
    $presenter = app(QuestionPresenter::class);

    $views = collect(range(1, 30))->map(fn ($seed) => collect($presenter->present($q, QuestionPresenter::MODE_EXAM, $seed)['options'])->pluck('key')->all());

    $views->each(fn ($keys) => expect($keys)->toHaveCount(4)->toContain('d'));
    expect($views->map(fn ($k) => implode(',', $k))->unique()->count())->toBeGreaterThan(5)       // order/subset varies
        ->and($views->map(fn ($k) => array_search('d', $k, true))->unique()->count())->toBe(4)   // answer moves position
        ->and(json_encode($presenter->present($q, QuestionPresenter::MODE_EXAM, 1)))->not->toContain('correct');
});

it('keeps the same option set for one attempt (resume shows identical options)', function () {
    $q = Question::factory()->create([
        'payload' => ['options' => array_map(fn ($i) => ['key' => chr(97 + $i), 'text' => "o{$i}"], range(0, 5))],
        'answer_key' => ['correct' => 'a'],
    ]);
    $presenter = app(QuestionPresenter::class);

    expect($presenter->present($q, QuestionPresenter::MODE_EXAM, 42))->toBe($presenter->present($q, QuestionPresenter::MODE_EXAM, 42));
});

it('generates many unique, correctly keyed variants for every topic', function (string $topic) {
    $items = app(TemplateQuestionGenerator::class)->generate($topic, 500, 7);
    $graders = app(GraderRegistry::class);

    expect(count($items))->toBeGreaterThanOrEqual(30)
        ->and(collect($items)->pluck('prompt')->unique()->count())->toBe(count($items));

    foreach ($items as $item) {
        $q = new Question($item + ['skill' => Skill::Grammar, 'points' => 1]);
        $answer = match ($q->type) {
            QuestionType::McqSingle => $item['answer_key']['correct'],
            QuestionType::FillBlank => [$item['answer_key']['blanks'][0][0]],
            default => $item['answer_key']['accepted'][0],
        };
        expect($graders->grade($q, $answer)->isCorrect)->toBeTrue("{$topic}: {$item['prompt']}");

        if ($q->type === QuestionType::McqSingle) {
            $texts = array_column($item['payload']['options'], 'text');
            expect(count($texts))->toBe(count(array_unique($texts)))->toBeGreaterThanOrEqual(4);
        }
    }
})->with(array_keys(TemplateQuestionGenerator::TOPICS));

it('gives two different generated samples to two lessons of the same topic', function () {
    $gen = app(TemplateQuestionGenerator::class);
    $a = collect($gen->generate('functional_language', 30, 1))->pluck('prompt');
    $b = collect($gen->generate('functional_language', 30, 2))->pluck('prompt');

    expect($a->diff($b)->count())->toBeGreaterThan(5);
});

it('never puts practice questions in unit exams when the pool has enough other questions', function () {
    $course = course(units: 1, lessons: 2);
    $exam = $course['units'][0]->exam()->first();
    $practice = DB::table('lesson_question')->pluck('question_id')->all();

    foreach (range(1, 5) as $_) {
        $ids = app(QuestionPicker::class)->pick($exam, student());
        expect(array_intersect($ids, $practice))->toBeEmpty();
    }
});

describe('with the A1 seed', function () {
    beforeEach(fn () => $this->seed(DatabaseSeeder::class));

    it('builds every lesson quiz only from items the learner never saw in practice', function () {
        $user = User::where('email', 'student@demo.test')->first();
        $picker = app(QuestionPicker::class);

        foreach (Lesson::with('questions', 'exam')->get() as $lesson) {
            $ids = $picker->pick($lesson->exam, $user);
            expect($ids)->toHaveCount($lesson->exam->question_count)
                ->and(array_intersect($ids, $lesson->questions->pluck('id')->all()))->toBeEmpty("{$lesson->slug} quiz reuses practice items");
        }
    });

    it('draws a different grammar quiz on retake', function () {
        $user = User::where('email', 'student@demo.test')->first();
        $lesson = Lesson::query()->where('type', 'grammar')->orderBy('id')->first();
        app(UnlockService::class)->initialize($user);
        app(LessonService::class)->complete($user, $lesson);
        $exams = app(ExamService::class);

        $first = $exams->start($user, $lesson->exam);
        $exams->submit($first); // nothing answered → fails, no cooldown for lesson quizzes
        $second = $exams->start($user, $lesson->exam);

        expect(array_intersect($first->question_ids, $second->question_ids))->toBeEmpty();
    });

    it('shuffles practice order on every visit', function () {
        $admin = User::where('email', 'admin@demo.test')->first();
        $lesson = Lesson::query()->orderBy('id')->first();
        $order = function () use ($admin, $lesson) {
            $html = $this->actingAs($admin)->get(route('lessons.show', ['lesson' => $lesson, 'preview' => 1]))->getContent();
            // @js() renders JSON.parse('[{\u0022id\u0022:12,\u0022type\u0022:…') — read the ids in order
            preg_match_all('/\\\\u0022id\\\\u0022:(\d+),\\\\u0022type\\\\u0022/', $html, $m);

            return implode(',', $m[1]);
        };

        $orders = collect(range(1, 4))->map(fn () => $order());
        expect($orders->first())->not->toBe('')
            ->and($orders->unique()->count())->toBeGreaterThan(1);
    });

    it('uses a different reading passage in the quiz than in the lesson', function () {
        $lesson = Lesson::query()->where('type', 'reading')->orderBy('id')->first();
        $practicePassage = $lesson->questions()->first()->payload['passage'];
        $quizIds = app(QuestionPicker::class)->pick($lesson->exam, User::first());

        Question::query()->whereIn('id', $quizIds)->get()->each(
            fn (Question $q) => expect($q->payload['passage'] ?? null)->not->toBeNull()->not->toBe($practicePassage)
        );
    });
});
