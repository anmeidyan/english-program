<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\LessonType;
use App\Enums\LevelCode;
use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionTag;
use App\Models\Unit;
use App\Models\Vocabulary;
use App\Services\Content\Generators\TemplateQuestionGenerator;
use App\Services\CurriculumService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Imports curriculum content in the format documented in docs/content-format.md.
 * Used by the seeders and by the admin JSON import job. Returns a per-row report.
 */
class ContentImportService
{
    /** @var list<string> */
    private array $errors = [];

    /** @var array{units:int, lessons:int, questions:int, vocabulary:int, skipped:int} */
    private array $stats = ['units' => 0, 'lessons' => 0, 'questions' => 0, 'vocabulary' => 0, 'skipped' => 0];

    public function __construct(
        private readonly ExamProvisioner $exams,
        private readonly CurriculumService $curriculum,
        private readonly TemplateQuestionGenerator $generator,
    ) {}

    /**
     * @param  array<string, mixed>  $data  {level, modules:[{title, slug?, description?, units:[unit...]}]}
     * @return array{errors: list<string>, stats: array<string, int>}
     */
    public function importLevel(array $data, bool $publish = true): array
    {
        $this->errors = [];
        $validator = Validator::make($data, [
            'level' => ['required', 'string'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.title' => ['required', 'string'],
            'modules.*.units' => ['required', 'array', 'min:1'],
        ]);
        if ($validator->fails()) {
            return ['errors' => $validator->errors()->all(), 'stats' => $this->stats];
        }

        $code = LevelCode::tryFrom(strtoupper(str_replace('-', '_', (string) $data['level'])));
        $level = $code ? Level::query()->where('code', $code->value)->first() : null;
        if ($level === null) {
            return ['errors' => ["Level \"{$data['level']}\" tidak ditemukan."], 'stats' => $this->stats];
        }

        DB::transaction(function () use ($data, $level, $publish) {
            $levelTag = strtolower($level->code->value);
            foreach (array_values($data['modules']) as $mi => $moduleData) {
                $module = Module::query()->updateOrCreate(
                    ['slug' => $moduleData['slug'] ?? Str::slug($levelTag.' '.$moduleData['title'])],
                    [
                        'level_id' => $level->id,
                        'title' => $moduleData['title'],
                        'description' => $moduleData['description'] ?? null,
                        'sort_order' => $mi + 1,
                        'is_published' => $publish,
                    ],
                );
                foreach (array_values($moduleData['units']) as $ui => $unitData) {
                    $this->importUnit($module, $unitData, $ui + 1, $levelTag, $publish, 'modules.'.$mi.'.units.'.$ui);
                }
            }
            if ($publish) {
                $level->update(['is_published' => true]);
            }
            $this->exams->forLevel($level, $levelTag);
        });

        $this->curriculum->flush();

        return ['errors' => $this->errors, 'stats' => $this->stats];
    }

    /** @param array<string, mixed> $data */
    private function importUnit(Module $module, array $data, int $order, string $levelTag, bool $publish, string $path): void
    {
        $validator = Validator::make($data, [
            'title' => ['required', 'string'],
            'slug' => ['required', 'string', 'alpha_dash'],
            'lessons' => ['required', 'array', 'min:1'],
            'lessons.*.title' => ['required', 'string'],
            'lessons.*.type' => ['required', 'in:'.implode(',', array_column(LessonType::cases(), 'value'))],
            'vocabulary' => ['array'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->errors[] = "{$path}: {$message}";
            }

            return;
        }

        $unit = Unit::query()->updateOrCreate(['slug' => $data['slug']], [
            'module_id' => $module->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'sort_order' => $order,
            'is_published' => $publish,
        ]);
        $this->stats['units']++;

        $unitTag = $unit->slug;
        $vocabIds = $this->importVocabulary($data['vocabulary'] ?? [], $module->level_id, $path);

        $lessons = [];
        foreach (array_values($data['lessons']) as $li => $lessonData) {
            $lesson = $this->importLesson($unit, $lessonData, $li + 1, [$levelTag, $unitTag], $vocabIds, $publish, "{$path}.lessons.{$li}");
            $lessons[] = [$lesson, ! empty($lessonData['vocab'])];
        }

        $vocabLessonTags = array_map(fn (array $l) => $l[0]->slug, array_filter($lessons, fn (array $l) => $l[1]));
        $this->generateVocabularyQuestions($vocabIds, $module->level_id, [$levelTag, $unitTag, ...$vocabLessonTags]);

        // quizzes are provisioned after every generated item exists, so their rules see the full pool
        foreach ($lessons as [$lesson]) {
            $this->exams->forLesson($lesson, $lesson->slug);
        }
        $this->exams->forUnit($unit, $unitTag);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $tags
     * @param  list<int>  $vocabIds
     */
    private function importLesson(Unit $unit, array $data, int $order, array $tags, array $vocabIds, bool $publish, string $path): Lesson
    {
        $type = LessonType::from($data['type']);
        $skill = Skill::tryFrom($data['skill'] ?? $type->value) ?? Skill::Grammar;
        $slug = $data['slug'] ?? $this->uniqueSlug($tags[0].' '.$data['title'], $unit->id);

        $lesson = Lesson::query()->updateOrCreate(['slug' => $slug], [
            'unit_id' => $unit->id,
            'title' => $data['title'],
            'type' => $type,
            'skill' => $skill,
            'content' => $data['blocks'] ?? [],
            'estimated_minutes' => (int) ($data['minutes'] ?? 15),
            'sort_order' => $order,
            'is_published' => $publish,
        ]);
        $this->stats['lessons']++;

        if (! empty($data['vocab'])) {
            $lesson->vocabularies()->syncWithoutDetaching($vocabIds);
        }

        $lessonTag = $lesson->slug;
        if ($lesson->questions()->exists()) {
            $this->stats['skipped']++;
        } else {
            foreach (array_values($data['questions'] ?? []) as $qi => $q) {
                $question = $this->createQuestion($q, $skill, $unit, $data, [...$tags, $lessonTag], "{$path}.questions.{$qi}");
                if ($question !== null) {
                    $lesson->questions()->attach($question->id, ['sort_order' => $qi + 1]);
                }
            }

            // quiz-only items (own passage/audio), never shown in practice so they cannot be memorised there
            $quizContext = ['passage' => $data['quiz_passage'] ?? null, 'audio' => $data['quiz_audio'] ?? null];
            foreach (array_values($data['quiz_questions'] ?? []) as $qi => $q) {
                $this->createQuestion($q, $skill, $unit, $quizContext, [...$tags, $lessonTag, 'quiz-only'], "{$path}.quiz_questions.{$qi}");
            }
        }

        if (! empty($data['generator'])) {
            $this->generateTemplateQuestions($lesson, $skill, (array) $data['generator'], [...$tags, $lessonTag], $path);
        }

        return $lesson;
    }

    /**
     * Quiz/exam-only variants from a template topic (never attached to practice), see
     * TemplateQuestionGenerator. Idempotent per lesson.
     *
     * @param  array{topic?: string, count?: int}  $config
     * @param  list<string>  $tags
     */
    private function generateTemplateQuestions(Lesson $lesson, Skill $skill, array $config, array $tags, string $path): void
    {
        $topic = (string) ($config['topic'] ?? '');
        if (! array_key_exists($topic, TemplateQuestionGenerator::TOPICS)) {
            $this->errors[] = "{$path}.generator: topik \"{$topic}\" tidak dikenal (".implode(', ', array_keys(TemplateQuestionGenerator::TOPICS)).').';

            return;
        }
        $already = Question::query()->whereHas('tags', fn ($q) => $q->where('name', 'generated-template'))
            ->whereHas('tags', fn ($q) => $q->where('name', $lesson->slug))->exists();
        if ($already) {
            return;
        }

        $levelId = $lesson->unit()->firstOrFail()->module()->value('level_id');
        $tagIds = $this->tagIds([...$tags, 'generated-template', 'topic-'.$topic]);
        foreach ($this->generator->generate($topic, (int) ($config['count'] ?? 40), $lesson->id) as $item) {
            $question = Question::query()->create($item + [
                'skill' => $skill,
                'level_id' => $levelId,
                'points' => 1,
                'is_active' => true,
            ]);
            $question->tags()->sync($tagIds);
            $this->stats['questions']++;
        }
    }

    /**
     * @param  array<string, mixed>  $q
     * @param  array<string, mixed>  $lessonData
     * @param  list<string>  $tags
     */
    private function createQuestion(array $q, Skill $skill, Unit $unit, array $lessonData, array $tags, string $path): ?Question
    {
        $type = QuestionType::tryFrom((string) ($q['type'] ?? ''));
        if ($type === null || empty($q['prompt'])) {
            $this->errors[] = "{$path}: tipe soal atau prompt tidak valid.";

            return null;
        }

        $payload = $q['payload'] ?? [];
        if ($skill === Skill::Reading && ! empty($lessonData['passage'])) {
            $payload['passage'] = $lessonData['passage'];
        }
        if ($skill === Skill::Listening && ! empty($lessonData['audio']) && $type !== QuestionType::Dictation) {
            $payload['tts_text'] = $lessonData['audio'];
            $payload['transcript'] = $lessonData['audio'];
        }

        $question = Question::query()->create([
            'type' => $type,
            'skill' => isset($q['skill']) ? Skill::from($q['skill']) : $skill,
            'level_id' => $unit->module()->value('level_id'),
            'difficulty' => (int) ($q['difficulty'] ?? 1),
            'prompt' => $q['prompt'],
            'payload' => $payload,
            'answer_key' => $q['answer_key'] ?? [],
            'explanation' => $q['explanation'] ?? null,
            'points' => (int) ($q['points'] ?? 1),
            'media_path' => $q['media_path'] ?? null,
            'is_active' => true,
        ]);
        $question->tags()->sync($this->tagIds($tags));
        $this->stats['questions']++;

        return $question;
    }

    /**
     * @param  list<array<int|string, mixed>>  $rows  [word, pos, ipa_us, ipa_uk, meaning_id, definition_en, example] or keyed
     * @return list<int>
     */
    private function importVocabulary(array $rows, int $levelId, string $path): array
    {
        $ids = [];
        foreach ($rows as $i => $row) {
            $row = array_is_list($row)
                ? array_combine(['word', 'part_of_speech', 'ipa_us', 'ipa_uk', 'meaning_id', 'definition_en', 'example'], array_pad($row, 7, null))
                : $row;
            if (empty($row['word']) || empty($row['meaning_id'])) {
                $this->errors[] = "{$path}.vocabulary.{$i}: word dan meaning_id wajib diisi.";

                continue;
            }
            $vocab = Vocabulary::query()->updateOrCreate(
                ['word' => $row['word'], 'level_id' => $levelId],
                collect($row)->only(['part_of_speech', 'ipa_us', 'ipa_uk', 'meaning_id', 'definition_en', 'example', 'audio_us', 'audio_uk', 'image_path'])->all(),
            );
            $ids[] = $vocab->id;
            $this->stats['vocabulary']++;
        }

        return $ids;
    }

    /**
     * Exam-pool variants per word (never shown in practice): meaning (MCQ), reverse meaning (MCQ),
     * listen-and-type, and — when the example sentence contains the word — fill in the blank.
     * MCQs carry up to 6 options; each presentation shows the answer + 3 random distractors.
     *
     * @param  list<int>  $vocabIds
     * @param  list<string>  $tags
     */
    private function generateVocabularyQuestions(array $vocabIds, int $levelId, array $tags): void
    {
        $words = Vocabulary::query()->whereIn('id', $vocabIds)->get();
        if ($words->count() < 4) {
            return;
        }
        $tagIds = $this->tagIds([...$tags, 'generated-vocabulary']);

        foreach ($words as $word) {
            $exists = Question::query()->where('skill', Skill::Vocabulary->value)
                ->where('payload->vocabulary_id', $word->id)->exists();
            if ($exists) {
                continue;
            }
            $rng = new Randomizer(new Mt19937(crc32($word->word)));
            $others = $words->where('id', '!=', $word->id)->values();
            $pick = fn (string $field) => array_slice($rng->shuffleArray($others->pluck($field)->unique()->values()->all()), 0, 5);

            $rows = [
                $this->choice("What is the meaning of \"{$word->word}\"?", $word->meaning_id, $pick('meaning_id'), $rng, 1),
                $this->choice("Which word means \"{$word->meaning_id}\"?", $word->word, $pick('word'), $rng, 1),
                [
                    'type' => QuestionType::Dictation, 'difficulty' => 2,
                    'prompt' => 'Listen and type the word you hear.',
                    'payload' => ['tts_text' => $word->word],
                    'answer_key' => ['text' => $word->word],
                ],
            ];

            $example = (string) $word->example;
            $pattern = '/\b'.preg_quote($word->word, '/').'\b/i';
            if ($example !== '' && preg_match($pattern, $example) === 1) {
                $rows[] = [
                    'type' => QuestionType::FillBlank, 'difficulty' => 2,
                    'prompt' => preg_replace($pattern, '___', $example, 1).' ('.$word->meaning_id.')',
                    'payload' => ['blanks' => 1],
                    'answer_key' => ['blanks' => [[$word->word]]],
                ];
            }

            foreach ($rows as $row) {
                $row['payload']['vocabulary_id'] = $word->id;
                $question = Question::query()->create($row + [
                    'skill' => Skill::Vocabulary, 'level_id' => $levelId, 'points' => 1, 'is_active' => true,
                    'explanation' => "{$word->word} = {$word->meaning_id}. {$word->example}",
                ]);
                $question->tags()->sync($tagIds);
                $this->stats['questions']++;
            }
        }
    }

    /**
     * @param  list<string>  $distractors
     * @return array<string, mixed>
     */
    private function choice(string $prompt, string $correct, array $distractors, Randomizer $rng, int $difficulty): array
    {
        $options = $rng->shuffleArray(array_values(array_unique([$correct, ...$distractors])));
        $keyed = [];
        $correctKey = 'a';
        foreach ($options as $i => $text) {
            $keyed[] = ['key' => chr(97 + $i), 'text' => $text];
            if ($text === $correct) {
                $correctKey = chr(97 + $i);
            }
        }

        return [
            'type' => QuestionType::McqSingle, 'difficulty' => $difficulty, 'prompt' => $prompt,
            'payload' => ['options' => $keyed, 'max_options' => 4],
            'answer_key' => ['correct' => $correctKey],
        ];
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function tagIds(array $names): array
    {
        return array_map(fn (string $name) => QuestionTag::query()->firstOrCreate(['name' => $name])->id, array_unique($names));
    }

    private function uniqueSlug(string $base, int $unitId): string
    {
        $slug = Str::slug($base);
        $candidate = $slug;
        $i = 2;
        while (($existing = Lesson::withTrashed()->where('slug', $candidate)->first()) !== null) {
            if ($existing->unit_id === $unitId) {
                return $candidate;
            }
            $candidate = "{$slug}-{$i}";
            $i++;
        }

        return $candidate;
    }
}
