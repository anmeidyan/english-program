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

        foreach (array_values($data['lessons']) as $li => $lessonData) {
            $this->importLesson($unit, $lessonData, $li + 1, [$levelTag, $unitTag], $vocabIds, $publish, "{$path}.lessons.{$li}");
        }

        $this->generateVocabularyQuestions($vocabIds, $module->level_id, [$levelTag, $unitTag]);
        $this->exams->forUnit($unit, $unitTag);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $tags
     * @param  list<int>  $vocabIds
     */
    private function importLesson(Unit $unit, array $data, int $order, array $tags, array $vocabIds, bool $publish, string $path): void
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
        }

        $this->exams->forLesson($lesson, $lessonTag);
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
     * Two exam-pool questions per word: meaning (MCQ) and listen-and-type.
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

        foreach ($words as $i => $word) {
            $exists = Question::query()->where('skill', Skill::Vocabulary->value)
                ->where('payload->vocabulary_id', $word->id)->exists();
            if ($exists) {
                continue;
            }

            $pool = $words->where('id', '!=', $word->id)->pluck('meaning_id')->values()->all();
            $distractors = array_slice((new Randomizer(new Mt19937(crc32($word->word))))->shuffleArray($pool), 0, 3);
            $options = [...$distractors];
            $correctIndex = $i % 4;
            array_splice($options, $correctIndex, 0, [$word->meaning_id]);
            $keyed = array_map(fn ($text, $k) => ['key' => chr(97 + $k), 'text' => $text], $options, array_keys($options));

            $mcq = Question::query()->create([
                'type' => QuestionType::McqSingle, 'skill' => Skill::Vocabulary, 'level_id' => $levelId, 'difficulty' => 1,
                'prompt' => "What is the meaning of \"{$word->word}\"?",
                'payload' => ['options' => $keyed, 'vocabulary_id' => $word->id],
                'answer_key' => ['correct' => chr(97 + $correctIndex)],
                'explanation' => "{$word->word} = {$word->meaning_id}. {$word->example}",
                'points' => 1, 'is_active' => true,
            ]);
            $dictation = Question::query()->create([
                'type' => QuestionType::Dictation, 'skill' => Skill::Vocabulary, 'level_id' => $levelId, 'difficulty' => 2,
                'prompt' => 'Listen and type the word you hear.',
                'payload' => ['tts_text' => $word->word, 'vocabulary_id' => $word->id],
                'answer_key' => ['text' => $word->word],
                'explanation' => "{$word->word} ({$word->meaning_id})",
                'points' => 1, 'is_active' => true,
            ]);
            $mcq->tags()->sync($tagIds);
            $dictation->tags()->sync($tagIds);
            $this->stats['questions'] += 2;
        }
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
