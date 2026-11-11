<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionTag;
use App\Models\TenseTopic;
use App\Services\Content\ExamProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the 16 Tenses Master topics and generates a checkpoint pool per tense
 * from a small conjugation engine (16 frames × 16 tenses, MCQ + fill-in alternating).
 */
class TenseSeeder extends Seeder
{
    /** [subject, pronoun, person: 1s|2|3s|1p|3p] */
    private const SUBJECTS = [
        ['Rina', 'she', '3s'], ['My brother', 'he', '3s'], ['I', 'I', '1s'], ['They', 'they', '3p'],
        ['We', 'we', '1p'], ['You', 'you', '2'], ['Tom', 'he', '3s'], ['The children', 'they', '3p'],
    ];

    /** [base, past, participle, ing, third-person, object] */
    private const FRAMES = [
        ['study', 'studied', 'studied', 'studying', 'studies', 'English'],
        ['play', 'played', 'played', 'playing', 'plays', 'football'],
        ['practise', 'practised', 'practised', 'practising', 'practises', 'the piano'],
        ['watch', 'watched', 'watched', 'watching', 'watches', 'TV'],
        ['clean', 'cleaned', 'cleaned', 'cleaning', 'cleans', 'the house'],
        ['cook', 'cooked', 'cooked', 'cooking', 'cooks', 'dinner'],
        ['write', 'wrote', 'written', 'writing', 'writes', 'emails'],
        ['work', 'worked', 'worked', 'working', 'works', 'in the garden'],
        ['run', 'ran', 'run', 'running', 'runs', 'in the park'],
        ['swim', 'swam', 'swum', 'swimming', 'swims', 'in the pool'],
        ['paint', 'painted', 'painted', 'painting', 'paints', 'the fence'],
        ['wash', 'washed', 'washed', 'washing', 'washes', 'the car'],
        ['read', 'read', 'read', 'reading', 'reads', 'the newspaper'],
        ['ride', 'rode', 'ridden', 'riding', 'rides', 'a bike'],
        ['do', 'did', 'done', 'doing', 'does', 'homework'],
        ['drive', 'drove', 'driven', 'driving', 'drives', 'to work'],
    ];

    /** sentence templates; {S} subject, {p} pronoun, {o} object */
    private const TEMPLATES = [
        'present_simple' => '{S} ___ {o} every day.',
        'present_continuous' => 'Look! {S} ___ {o} right now.',
        'present_perfect' => '{S} ___ {o} three times so far this week.',
        'present_perfect_continuous' => '{S} ___ {o} for two hours now.',
        'past_simple' => '{S} ___ {o} yesterday.',
        'past_continuous' => 'At this time yesterday, {s} ___ {o}.',
        'past_perfect' => '{S} ___ {o} by the time the guests arrived.',
        'past_perfect_continuous' => '{S} ___ {o} for an hour when the power went out.',
        'future_simple' => '{S} ___ {o} tomorrow.',
        'future_continuous' => 'This time tomorrow, {s} ___ {o}.',
        'future_perfect' => 'By next Friday, {s} ___ {o}.',
        'future_perfect_continuous' => 'By 6 p.m., {s} ___ {o} for three hours.',
        'past_future_simple' => '{S} said that {p} ___ {o} the next day.',
        'past_future_continuous' => '{S} said that {p} ___ {o} at that time the next day.',
        'past_future_perfect' => '{S} said that {p} ___ {o} by the end of the week.',
        'past_future_perfect_continuous' => '{S} said that by June {p} ___ {o} for a year.',
    ];

    /** distractors: plausible-looking but wrong for the time signal in the template */
    private const NEIGHBOURS = [
        'present_simple' => ['present_perfect', 'past_perfect', 'future_perfect'],
        'present_continuous' => ['present_simple', 'past_continuous', 'present_perfect_continuous'],
        'present_perfect' => ['past_perfect', 'present_simple', 'future_perfect'],
        'present_perfect_continuous' => ['present_continuous', 'present_simple', 'past_perfect_continuous'],
        'past_simple' => ['present_perfect', 'present_simple', 'past_perfect'],
        'past_continuous' => ['present_continuous', 'present_simple', 'past_perfect_continuous'],
        'past_perfect' => ['present_perfect', 'present_simple', 'past_perfect_continuous'],
        'past_perfect_continuous' => ['past_simple', 'present_perfect_continuous', 'present_continuous'],
        'future_simple' => ['present_simple', 'past_simple', 'present_perfect'],
        'future_continuous' => ['future_perfect', 'present_continuous', 'past_continuous'],
        'future_perfect' => ['future_continuous', 'present_perfect', 'past_perfect'],
        'future_perfect_continuous' => ['future_simple', 'future_continuous', 'present_perfect_continuous'],
        'past_future_simple' => ['future_simple', 'past_simple', 'present_simple'],
        'past_future_continuous' => ['present_continuous', 'past_continuous', 'future_continuous'],
        'past_future_perfect' => ['past_perfect', 'future_perfect', 'past_simple'],
        'past_future_perfect_continuous' => ['past_future_simple', 'past_perfect_continuous', 'future_perfect_continuous'],
    ];

    public function run(ExamProvisioner $exams): void
    {
        $topics = require database_path('seeders/content/tenses.php');
        $levels = Level::query()->pluck('id', 'code');

        foreach ($topics as $i => [$key, $name, $time, $aspect, $level, $position, $formula, $function, $signals, $examples, $compare, $mistake]) {
            $topic = TenseTopic::query()->updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'time' => $time,
                'aspect' => $aspect,
                'formula' => ['positive' => $formula[0], 'negative' => $formula[1], 'question' => $formula[2]],
                'content' => [
                    'key' => $key,
                    'timeline' => $position,
                    'timeline_label' => $name,
                    'function' => $function,
                    'signals' => $signals,
                    'examples' => $examples,
                    'compare' => $compare,
                    'mistake' => ['wrong' => $mistake[0], 'right' => $mistake[1], 'note' => $mistake[2]],
                ],
                'required_level_id' => $levels[$level] ?? null,
                'sort_order' => $i + 1,
                'is_published' => true,
            ]);

            $tag = 'tense-'.$key;
            if (! Question::query()->whereHas('tags', fn ($q) => $q->where('name', $tag))->exists()) {
                $this->generate($key, $tag, $levels[$level] ?? null);
            }
            $exams->forTense($topic, $tag);
        }
    }

    private function generate(string $tense, string $tagName, ?int $levelId): void
    {
        $tagIds = [
            QuestionTag::query()->firstOrCreate(['name' => $tagName])->id,
            QuestionTag::query()->firstOrCreate(['name' => 'tenses-master'])->id,
        ];

        foreach (self::FRAMES as $n => $frame) {
            [$subject, $pronoun, $person] = self::SUBJECTS[$n % count(self::SUBJECTS)];
            $correct = self::form($tense, $frame, $person);
            $sentence = strtr(self::TEMPLATES[$tense], [
                '{S}' => $subject,
                '{s}' => $subject === 'I' ? 'I' : lcfirst($subject),
                '{p}' => $pronoun,
                '{o}' => $frame[5],
            ]);
            // proper nouns keep their capital letter
            $sentence = preg_replace_callback('/\b(rina|tom)\b/', fn ($m) => ucfirst($m[1]), $sentence) ?? $sentence;

            if ($n % 2 === 0) {
                $options = array_values(array_unique([$correct, ...array_map(fn ($t) => self::form($t, $frame, $person), self::NEIGHBOURS[$tense])]));
                $correctIndex = $n % count($options);
                $others = array_values(array_diff($options, [$correct]));
                array_splice($others, $correctIndex, 0, [$correct]);
                $data = [
                    'type' => QuestionType::McqSingle,
                    'prompt' => $sentence,
                    'payload' => ['options' => array_map(fn ($t, $k) => ['key' => chr(97 + $k), 'text' => $t], $others, array_keys($others))],
                    'answer_key' => ['correct' => chr(97 + $correctIndex)],
                ];
            } else {
                $data = [
                    'type' => QuestionType::FillBlank,
                    'prompt' => str_replace('___', '___ ('.$frame[0].')', $sentence),
                    'payload' => ['blanks' => 1],
                    'answer_key' => ['blanks' => [[$correct]]],
                ];
            }

            $question = Question::query()->create($data + [
                'skill' => Skill::Grammar,
                'level_id' => $levelId,
                'difficulty' => 2,
                'explanation' => 'Jawaban: "'.$correct.'". Gunakan bentuk lengkap (tanpa singkatan).',
                'points' => 1,
                'is_active' => true,
            ]);
            $question->tags()->sync($tagIds);
        }
    }

    /** @param array{0:string,1:string,2:string,3:string,4:string,5:string} $v */
    public static function form(string $tense, array $v, string $person): string
    {
        [$base, $past, $pp, $ing, $third] = $v;
        $third_ = $person === '3s';
        $be = match ($person) {
            '1s' => 'am', '3s' => 'is', default => 'are'
        };
        $was = in_array($person, ['1s', '3s'], true) ? 'was' : 'were';
        $have = $third_ ? 'has' : 'have';

        return match ($tense) {
            'present_simple' => $third_ ? $third : $base,
            'present_continuous' => "$be $ing",
            'present_perfect' => "$have $pp",
            'present_perfect_continuous' => "$have been $ing",
            'past_simple' => $past,
            'past_continuous' => "$was $ing",
            'past_perfect' => "had $pp",
            'past_perfect_continuous' => "had been $ing",
            'future_simple' => "will $base",
            'future_continuous' => "will be $ing",
            'future_perfect' => "will have $pp",
            'future_perfect_continuous' => "will have been $ing",
            'past_future_simple' => "would $base",
            'past_future_continuous' => "would be $ing",
            'past_future_perfect' => "would have $pp",
            'past_future_perfect_continuous' => "would have been $ing",
        };
    }
}
