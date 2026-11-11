<?php

declare(strict_types=1);

namespace App\Services\Content\Generators;

use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds many concrete variants of a grammar point from templates (subject × verb × object × form),
 * so quizzes and exams draw items the learner has never seen in practice and cannot memorise.
 * Each topic yields only items with exactly one grammatical answer; distractors are drawn from a
 * larger set and sub-sampled at presentation time.
 *
 * Output rows match the `questions` table (type, prompt, payload, answer_key, explanation, difficulty).
 */
class TemplateQuestionGenerator
{
    /** [subject, pronoun, person] — person: 1s, 2, 3s, 1p, 3p */
    private const SUBJECTS = [
        ['I', 'I', '1s'], ['You', 'you', '2'], ['She', 'she', '3s'], ['He', 'he', '3s'],
        ['Rina', 'she', '3s'], ['My brother', 'he', '3s'], ['We', 'we', '1p'], ['They', 'they', '3p'],
        ['My parents', 'they', '3p'], ['Tom and Ana', 'they', '3p'],
    ];

    /** @var array<string, string> */
    public const TOPICS = [
        'to_be' => 'Verb to be: am / is / are',
        'possessives' => "Possessive adjectives & 's",
        'there_is_are' => 'There is / there are + prepositions of place',
        'present_simple' => 'Simple Present + prepositions of time',
        'do_does' => "Simple Present: do / does / don't / doesn't",
        'present_continuous' => 'Present Continuous',
        'functional_language' => 'Functional language (respons yang tepat)',
        'writing_mechanics' => 'Capital letters, punctuation, and / but',
    ];

    private Randomizer $rng;

    /** @return list<array<string, mixed>> */
    public function generate(string $topic, int $count, int $seed = 1): array
    {
        $this->rng = new Randomizer(new Mt19937($seed ^ crc32($topic)));

        $all = match ($topic) {
            'to_be' => $this->toBe(),
            'possessives' => $this->possessives(),
            'there_is_are' => $this->thereIsAre(),
            'present_simple' => $this->presentSimple(),
            'do_does' => $this->doDoes(),
            'present_continuous' => $this->presentContinuous(),
            'functional_language' => $this->functionalLanguage(),
            'writing_mechanics' => $this->writingMechanics(),
            default => throw new InvalidArgumentException("Unknown generator topic [{$topic}]."),
        };

        // de-duplicate on prompt, then take a random sample
        $unique = [];
        foreach ($all as $item) {
            $unique[$item['prompt']] ??= $item;
        }
        $items = array_values($unique);

        return array_slice($this->rng->shuffleArray($items), 0, $count);
    }

    // ------------------------------------------------------------------ topics

    /** @return list<array<string, mixed>> */
    private function toBe(): array
    {
        $complements = ['from Japan', 'at home', 'tired', 'happy', 'in the classroom', 'from Surabaya', 'at school', 'hungry', 'ready', 'busy', 'at the park', 'cold'];
        $out = [];
        foreach (self::SUBJECTS as [$subject, $pronoun, $person]) {
            $be = $this->be($person);
            foreach ($complements as $c) {
                $out[] = $this->mcq("{$subject} ___ {$c}.", $be, ['am', 'is', 'are', 'be', 'was'], "{$subject} → {$be}.", 1);
                $out[] = $this->mcq("{$subject} ___ not {$c}.", $be, ['am', 'is', 'are', 'be', 'do'], "Negatif: {$subject} {$be} not …", 1);
                $q = ucfirst($be);
                $out[] = $this->mcq("___ {$this->lower($subject)} {$c}?", $q, ['Am', 'Is', 'Are', 'Do', 'Be'], "Pertanyaan: {$q} + {$this->lower($subject)} …?", 2);
                $wrong = $be === 'is' ? 'are' : 'is';
                $out[] = $this->sentence('error_correction', "Temukan dan perbaiki kesalahan pada kalimat berikut: \"{$subject} {$wrong} {$c}.\"",
                    ['sentence' => "{$subject} {$wrong} {$c}."], ["{$subject} {$be} {$c}."], "{$subject} → {$be}.", 2);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function possessives(): array
    {
        $owners = [
            ['Ana', 'her', '3s'], ['Budi', 'his', '3s'], ['My parents', 'their', '3p'], ['We', 'our', '1p'],
            ['I', 'my', '1s'], ['You', 'your', '2'], ['The children', 'their', '3p'], ['My sister', 'her', '3s'], ['Mr. Brown', 'his', '3s'],
        ];
        $things = ['car', 'house', 'bag', 'phone', 'bike', 'garden', 'computer', 'umbrella', 'dog', 'book'];
        $all = ['my', 'your', 'his', 'her', 'its', 'our', 'their'];
        $out = [];
        foreach ($owners as [$owner, $poss, $person]) {
            $have = $person === '3s' ? 'has' : 'have';
            foreach ($things as $thing) {
                $article = in_array($thing[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
                $out[] = $this->mcq("{$owner} {$have} {$article} {$thing}. It is ___ {$thing}.", $poss, $all, "{$owner} → {$poss}.", 1);
            }
        }
        foreach (['dog', 'cat', 'bird'] as $animal) {
            foreach (['ball', 'bed', 'food'] as $thing) {
                $out[] = $this->mcq("The {$animal} has a {$thing}. It is ___ {$thing}.", 'its', $all, 'Benda/hewan → its (bukan it\'s).', 2);
            }
        }
        foreach (['Rina', 'Tom', 'Siti', 'David', 'Ana', 'Budi'] as $name) {
            foreach (['bag', 'phone', 'bike', 'book'] as $thing) {
                $out[] = $this->mcq("This {$thing} belongs to {$name}. It is ___ {$thing}.", "{$name}'s", ["{$name}'s", $name, "{$name}s", "{$name} is", "{$name}s'"], "Pemilik + 's: {$name}'s {$thing}.", 2);
                $out[] = $this->sentence('transformation', "Tulis dengan 's: the {$thing} of {$name}", [], ["{$name}'s {$thing}"], "Pemilik + 's + benda.", 2);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function thereIsAre(): array
    {
        $nouns = [
            ['a sofa', 'is', ['in the living room', 'next to the window']],
            ['two chairs', 'are', ['in the kitchen', 'next to the table']],
            ['a lamp', 'is', ['on the desk', 'next to the bed']],
            ['three windows', 'are', ['in my bedroom', 'in the living room']],
            ['a cat', 'is', ['under the table', 'on the sofa']],
            ['some books', 'are', ['on the shelf', 'on the desk']],
            ['a garden', 'is', ['behind the house', 'in front of the house']],
            ['four bedrooms', 'are', ['in the house', 'upstairs']],
            ['an apple', 'is', ['on the table', 'in the bag']],
            ['two bathrooms', 'are', ['in the flat', 'upstairs']],
            ['a picture', 'is', ['on the wall', 'above the bed']],
            ['some keys', 'are', ['on the shelf', 'in the drawer']],
        ];
        $out = [];
        foreach ($nouns as [$np, $be, $places]) {
            foreach ($places as $place) {
                $out[] = $this->mcq("There ___ {$np} {$place}.", $be, ['is', 'are', 'am', 'be', 'have'], "{$np} → there {$be}.", 1);
                $q = ucfirst($be);
                $out[] = $this->mcq("___ there {$np} {$place}?", $q, ['Is', 'Are', 'Do', 'Does', 'Am'], "Pertanyaan: {$q} there …?", 2);
            }
        }

        $preps = ['in' => 'di dalam', 'on' => 'di atas', 'under' => 'di bawah', 'next to' => 'di sebelah', 'behind' => 'di belakang', 'in front of' => 'di depan'];
        foreach (['cat', 'ball', 'book', 'bag', 'phone', 'shoe'] as $thing) {
            foreach (['table', 'bed', 'sofa', 'box', 'door'] as $place) {
                foreach ($preps as $prep => $indo) {
                    $natural = match ($place) {
                        'box' => true,
                        'door' => in_array($prep, ['next to', 'behind', 'in front of'], true),
                        default => $prep !== 'in', // "in the table/bed/sofa" is unnatural
                    };
                    if (! $natural) {
                        continue;
                    }
                    $out[] = $this->mcq("The {$thing} is ___ the {$place}. ({$indo})", $prep, array_keys($preps), "{$indo} = {$prep}.", 1);
                }
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function presentSimple(): array
    {
        $verbs = [
            ['study', 'studies', 'studying', 'studied', 'English'], ['play', 'plays', 'playing', 'played', 'football'],
            ['watch', 'watches', 'watching', 'watched', 'TV'], ['go', 'goes', 'going', 'went', 'to school'],
            ['cook', 'cooks', 'cooking', 'cooked', 'dinner'], ['read', 'reads', 'reading', 'read', 'the newspaper'],
            ['drink', 'drinks', 'drinking', 'drank', 'coffee'], ['wash', 'washes', 'washing', 'washed', 'the dishes'],
            ['do', 'does', 'doing', 'did', 'homework'], ['have', 'has', 'having', 'had', 'breakfast'],
            ['take', 'takes', 'taking', 'took', 'a shower'], ['walk', 'walks', 'walking', 'walked', 'to work'],
        ];
        $signals = ['every day', 'every morning', 'on Sundays', 'every evening', 'twice a week'];
        $out = [];
        foreach (self::SUBJECTS as [$subject, , $person]) {
            foreach ($verbs as $i => [$base, $third, $ing, $past, $object]) {
                $signal = $signals[$i % count($signals)];
                $correct = $person === '3s' ? $third : $base;
                $other = $person === '3s' ? $base : $third;
                $be = $this->be($person);
                $out[] = $this->mcq("{$subject} ___ {$object} {$signal}.", $correct, [$correct, $other, "{$be} {$ing}", $past, $ing], "Kebiasaan ({$signal}) → Simple Present: {$subject} {$correct}.", 1);
                if ($i % 3 === 0) {
                    $out[] = $this->fill("{$subject} ___ ({$base}) {$object} {$signal}.", [$correct], "{$subject} → {$correct}.", 2);
                }
            }
        }

        $times = [
            ['12:30', 'at'], ["seven o'clock", 'at'], ['night', 'at'], ['noon', 'at'],
            ['Monday', 'on'], ['Sunday morning', 'on'], ['Friday', 'on'], ['17 August', 'on'],
            ['the morning', 'in'], ['July', 'in'], ['the evening', 'in'], ['the afternoon', 'in'],
        ];
        $activities = ['I go to the gym', 'She visits her grandmother', 'We have lunch', 'They play football', 'My father works', 'The shop opens'];
        foreach ($activities as $activity) {
            foreach ($times as [$time, $prep]) {
                $out[] = $this->mcq("{$activity} ___ {$time}.", $prep, ['at', 'on', 'in', 'to'], "at = jam/waktu tertentu, on = hari/tanggal, in = bagian hari/bulan/tahun → {$prep} {$time}.", 1);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function doDoes(): array
    {
        $items = [['eat', 'fish'], ['eat', 'vegetables'], ['drink', 'coffee'], ['drink', 'milk'], ['like', 'tea'], ['eat', 'rice'], ['like', 'bread'], ['drink', 'juice'], ['like', 'chicken'], ['eat', 'eggs']];
        $out = [];
        foreach (self::SUBJECTS as [$subject, $pronoun, $person]) {
            $do = $person === '3s' ? 'does' : 'do';
            foreach ($items as [$verb, $item]) {
                $out[] = $this->mcq("{$subject} ___ {$verb} {$item}.", "{$do}n't", ["don't", "doesn't", "isn't", "aren't", 'not'], "{$subject} → {$do}n't + kata kerja dasar.", 1);
                $out[] = $this->mcq('___ '.$this->lower($subject)." {$verb} {$item}?", ucfirst($do), ['Do', 'Does', 'Is', 'Are', 'Am'], 'Pertanyaan: '.ucfirst($do).' + '.$this->lower($subject).' + kata kerja dasar.', 1);
                if ($person === '3s') {
                    $out[] = $this->mcq("Does {$this->lower($subject)} {$verb} {$item}? — Yes, {$pronoun} ___.", 'does', ['does', 'do', 'is', "{$verb}s", 'has'], 'Jawaban singkat: Yes, he/she does.', 2);
                    $out[] = $this->sentence('error_correction', "Temukan dan perbaiki kesalahan pada kalimat berikut: \"Does {$this->lower($subject)} {$verb}s {$item}?\"",
                        ['sentence' => "Does {$this->lower($subject)} {$verb}s {$item}?"], ["Does {$this->lower($subject)} {$verb} {$item}?"], 'Setelah does → kata kerja dasar.', 2);
                } else {
                    $out[] = $this->sentence('transformation', "Ubah menjadi pertanyaan: \"{$subject} {$verb} {$item}.\"", [],
                        ['Do '.$this->lower($subject)." {$verb} {$item}?"], 'Do + subject + kata kerja dasar?', 2);
                }
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function presentContinuous(): array
    {
        $verbs = [
            ['read', 'reading', 'reads', 'a book'], ['write', 'writing', 'writes', 'an email'], ['swim', 'swimming', 'swims', 'in the pool'],
            ['play', 'playing', 'plays', 'football'], ['cook', 'cooking', 'cooks', 'dinner'], ['watch', 'watching', 'watches', 'TV'],
            ['run', 'running', 'runs', 'in the park'], ['dance', 'dancing', 'dances', 'at the party'], ['sing', 'singing', 'sings', 'a song'],
            ['wait', 'waiting', 'waits', 'for the bus'], ['sit', 'sitting', 'sits', 'on the sofa'], ['make', 'making', 'makes', 'a cake'],
        ];
        $signals = ['Look!', 'Listen!', 'Right now,', 'At the moment,'];
        $out = [];
        foreach (self::SUBJECTS as $si => [$subject, , $person]) {
            $be = $this->be($person);
            $wasWere = in_array($person, ['1s', '3s'], true) ? 'was' : 'were';
            foreach ($verbs as $i => [$base, $ing, $third, $object]) {
                $signal = $signals[($si + $i) % count($signals)];
                $s = in_array($signal, ['Look!', 'Listen!'], true) ? $subject : $this->lower($subject);
                $simple = $person === '3s' ? $third : $base;
                $out[] = $this->mcq("{$signal} {$s} ___ {$object}.", "{$be} {$ing}", ["{$be} {$ing}", $simple, $ing, "{$wasWere} {$ing}", "{$be} {$base}"], "Sedang terjadi sekarang → {$be} + {$ing}.", 1);
                if ($i % 3 === 1) {
                    $out[] = $this->fill("{$signal} {$s} ___ ({$base}) {$object}.", ["{$be} {$ing}"], "{$be} + V-ing.", 2);
                }
            }
        }

        $spelling = [
            ['run', 'running', ['runing', 'runeing', 'runnning']], ['write', 'writing', ['writeing', 'writting', 'writng']],
            ['swim', 'swimming', ['swiming', 'swimeing', 'swimmming']], ['dance', 'dancing', ['danceing', 'dancting', 'dancng']],
            ['sit', 'sitting', ['siting', 'siteing', 'sittting']], ['make', 'making', ['makeing', 'makking', 'makng']],
            ['lie', 'lying', ['lieing', 'liing', 'lyeing']], ['play', 'playing', ['plaing', 'playying', 'pleying']],
            ['stop', 'stopping', ['stoping', 'stopeing', 'stoppping']], ['come', 'coming', ['comeing', 'comming', 'comng']],
        ];
        foreach ($spelling as [$base, $ing, $wrong]) {
            $out[] = $this->mcq("{$base} → ___", $ing, [$ing, ...$wrong], "Ejaan -ing: {$base} → {$ing}.", 2);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function functionalLanguage(): array
    {
        // [what someone says, best reply, wrong replies]
        $bank = [
            ['Nice to meet you.', 'Nice to meet you too.', ["I'm fine, thanks.", 'Yes, I am.', 'Goodbye.', "You're welcome."]],
            ['How are you?', "I'm fine, thanks. And you?", ["I'm from Bali.", 'Nice to meet you.', "I'm a student.", 'Yes, I do.']],
            ['Where are you from?', "I'm from Indonesia.", ["I'm twenty.", "I'm fine.", "It's Monday.", 'Yes, I am.']],
            ["What's your name?", 'My name is Budi.', ["I'm from Bandung.", "I'm fine.", "It's a pen.", 'Yes, it is.']],
            ['Thank you very much!', "You're welcome.", ['Nice to meet you.', 'Yes, please.', "I'm sorry.", 'Me too.']],
            ['Can I have a coffee, please?', 'Sure. Small or large?', ['Yes, I am.', "You're welcome.", 'No, I am not.', "It's Tuesday."]],
            ['How much is the sandwich?', "It's five dollars.", ["It's delicious.", "It's on the table.", 'Yes, it is.', "It's two o'clock."]],
            ['What time is it?', "It's half past three.", ["It's Monday.", "It's hot.", "It's my watch.", "It's ten dollars."]],
            ['Do you like fish?', 'Yes, I do.', ['Yes, I am.', 'Yes, I like.', 'Yes, it does.', 'No, I am not.']],
            ['Is there a bank near here?', "Yes, there is. It's next to the post office.", ['Yes, it is.', 'Yes, I am.', 'No, I don\'t.', "It's five dollars."]],
            ['Can you open the window, please?', 'Sure, no problem.', ["You're welcome.", 'Yes, I can swim.', 'Nice to meet you.', "It's open on Monday."]],
            ['What are you doing?', "I'm cooking dinner.", ['I cook dinner.', "I'm fine.", "I'm a cook.", 'Yes, I am.']],
            ["I'm sorry I'm late.", "That's OK.", ["You're welcome.", 'Nice to meet you.', 'Me too.', 'Yes, please.']],
            ['How old are you?', "I'm twenty.", ["I'm fine.", "I'm from Solo.", "I'm a student.", "It's old."]],
            ['What do you do?', "I'm a teacher.", ["I'm fine, thanks.", "I'm doing my homework now.", 'Yes, I do.', "I'm twenty."]],
            ['Excuse me, where is the bathroom?', "It's upstairs, on the left.", ["It's very clean.", 'Yes, there is.', "It's two o'clock.", "You're welcome."]],
            ['Have a nice weekend!', 'Thanks, you too!', ['Yes, I have.', "You're welcome.", "I'm sorry.", 'Nice to meet you.']],
            ["What's the weather like today?", "It's sunny and hot.", ['I like the weather.', "It's Monday.", 'Yes, it is.', "It's ten o'clock."]],
            ['Would you like some tea?', 'Yes, please.', ['Yes, I would like.', "You're welcome.", 'Yes, I like.', 'Nice to meet you.']],
            ['Can you speak English?', 'Yes, a little.', ['Yes, I speak.', 'Yes, I am.', 'Yes, it can.', 'No, I am not.']],
            ['Whose bag is this?', "It's Rina's.", ["It's a bag.", "It's on the chair.", "It's blue.", 'Yes, it is.']],
            ["Let's go to the park!", 'Good idea!', ["You're welcome.", "I'm sorry.", 'Yes, I am.', 'Nice to meet you.']],
        ];
        $frames = ['Someone says: "%s" What is the best reply?', 'Your friend says: "%s" You answer:', '"%s" — Choose the best response.'];
        $out = [];
        foreach ($bank as [$line, $reply, $wrong]) {
            foreach ($frames as $frame) {
                $out[] = $this->mcq(sprintf($frame, $line), $reply, $wrong, "\"{$line}\" → \"{$reply}\"", 1);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function writingMechanics(): array
    {
        $out = [];
        $people = ['Rina', 'Tom', 'Kenji', 'Laura', 'Budi', 'Maria'];
        $places = [['Japan', 'Osaka'], ['Indonesia', 'Medan'], ['Brazil', 'Rio'], ['Canada', 'Toronto'], ['Australia', 'Sydney'], ['England', 'London']];
        foreach ($people as $i => $name) {
            foreach ($places as $j => [$country, $city]) {
                $right = "{$name} is from {$city}, {$country}.";
                $lower = fn (string $t) => strtolower($t);
                $out[] = $this->mcq('Which sentence has correct capital letters?', $right, [
                    "{$lower($name)} is from {$city}, {$country}.",
                    "{$name} is from {$lower($city)}, {$lower($country)}.",
                    strtolower($right),
                    "{$name} Is From {$city}, {$country}.",
                ], 'Huruf kapital: awal kalimat, nama orang, kota, negara.', 1);
                if (($i + $j) % 2 === 0) {
                    $out[] = $this->sentence('error_correction', 'Temukan dan perbaiki kesalahan pada kalimat berikut: "'.strtolower($right).'"',
                        ['sentence' => strtolower($right)], [$right], 'Huruf kapital: awal kalimat, nama orang, kota, negara.', 2);
                }
            }
        }
        foreach (['my friend and i are students.' => 'My friend and I are students.', 'my sister and i like tea.' => 'My sister and I like tea.', 'tom and i are from london.' => 'Tom and I are from London.', 'on sunday i visit my grandmother.' => 'On Sunday I visit my grandmother.'] as $wrong => $right) {
            $out[] = $this->sentence('error_correction', "Temukan dan perbaiki kesalahan pada kalimat berikut: \"{$wrong}\"", ['sentence' => $wrong], [$right], 'Kata "I", nama, dan hari selalu kapital.', 2);
        }

        $statements = ['My sister is a nurse', 'We live in Jakarta', 'The bus is late', 'I have two brothers', 'They are at home', 'Tom likes coffee'];
        $questions = ['Where do you live', 'Is she your sister', 'What time is it', 'Do you like tea', 'How old is your brother', 'Are they at school'];
        foreach ($questions as $i => $q) {
            $others = array_slice(array_merge(array_slice($statements, $i), array_slice($statements, 0, $i)), 0, 4);
            $out[] = $this->mcq('Which sentence needs a question mark (?)', $q, $others, 'Kalimat tanya diakhiri tanda tanya.', 1);
        }

        $joins = [
            ['I like tea', "I don't like coffee", 'but'], ['My father is a doctor', 'my mother is a teacher', 'and'],
            ['The flat is small', "it's very comfortable", 'but'], ['I have a brother', 'a sister', 'and'],
            ['She can swim', "she can't ride a bike", 'but'], ['We get up early', 'we have breakfast together', 'and'],
            ['The food is cheap', "it isn't very good", 'but'], ['Tom plays football', 'his sister plays tennis', 'and'],
        ];
        foreach ($joins as [$first, $second, $word]) {
            $out[] = $this->mcq("{$first} ___ {$second}.", $word, ['and', 'but', 'or', 'because', 'so'], $word === 'but' ? 'Dua ide yang berlawanan → but.' : 'Menambah informasi → and.', 1);
        }

        return $out;
    }

    // ------------------------------------------------------------------ builders

    /**
     * MCQ with every candidate as an option (the presenter shows the correct one + 3 random others).
     *
     * @param  list<string>  $candidates
     * @return array<string, mixed>
     */
    private function mcq(string $prompt, string $correct, array $candidates, string $explanation, int $difficulty): array
    {
        $options = array_values(array_unique([$correct, ...$candidates]));
        $options = $this->rng->shuffleArray($options);
        $keyed = [];
        $correctKey = 'a';
        foreach ($options as $i => $text) {
            $key = chr(97 + $i);
            $keyed[] = ['key' => $key, 'text' => $text];
            if ($text === $correct) {
                $correctKey = $key;
            }
        }

        return [
            'type' => 'mcq_single',
            'prompt' => $prompt,
            'payload' => ['options' => $keyed, 'max_options' => 4],
            'answer_key' => ['correct' => $correctKey],
            'explanation' => $explanation,
            'difficulty' => $difficulty,
        ];
    }

    /**
     * @param  list<string>  $accepted
     * @return array<string, mixed>
     */
    private function fill(string $prompt, array $accepted, string $explanation, int $difficulty): array
    {
        return [
            'type' => 'fill_blank',
            'prompt' => $prompt,
            'payload' => ['blanks' => 1],
            'answer_key' => ['blanks' => [$accepted]],
            'explanation' => $explanation,
            'difficulty' => $difficulty,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $accepted
     * @return array<string, mixed>
     */
    private function sentence(string $type, string $prompt, array $payload, array $accepted, string $explanation, int $difficulty): array
    {
        return [
            'type' => $type,
            'prompt' => $prompt,
            'payload' => $payload,
            'answer_key' => ['accepted' => $accepted],
            'explanation' => $explanation,
            'difficulty' => $difficulty,
        ];
    }

    private function be(string $person): string
    {
        return match ($person) {
            '1s' => 'am',
            '3s' => 'is',
            default => 'are',
        };
    }

    /** Lower-case pronouns/common nouns after a question word; names stay capitalised. */
    private function lower(string $subject): string
    {
        return in_array($subject, ['I', 'Rina', 'Tom and Ana', 'Ana', 'Budi', 'Mr. Brown'], true) ? $subject : lcfirst($subject);
    }
}
