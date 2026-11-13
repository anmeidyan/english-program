<?php

declare(strict_types=1);

namespace App\Services\Speaking;

/**
 * Offline coach: catches the mistakes Indonesian learners make most often (to be, third-person -s,
 * "I have 20 years old", did + past, a/an, modal + to, uncountable plurals, double comparatives)
 * and walks through the topic's follow-up questions. Used when no AI coach is configured or it fails.
 */
class RuleBasedSpeakingCoach implements SpeakingCoachInterface
{
    private const VERBS = ['go', 'do', 'have', 'like', 'want', 'work', 'live', 'eat', 'play', 'watch', 'study', 'need', 'make', 'take',
        'get', 'come', 'read', 'walk', 'drink', 'speak', 'know', 'think', 'love', 'cook', 'teach', 'try', 'say', 'see', 'wash',
        'finish', 'use', 'help', 'visit', 'drive', 'sleep', 'wake', 'buy', 'write', 'listen', 'run', 'swim', 'stay', 'enjoy', 'hate', 'feel'];

    private const PAST_TO_BASE = ['went' => 'go', 'ate' => 'eat', 'saw' => 'see', 'came' => 'come', 'bought' => 'buy', 'made' => 'make',
        'took' => 'take', 'got' => 'get', 'had' => 'have', 'did' => 'do', 'said' => 'say', 'wrote' => 'write', 'read' => 'read',
        'drank' => 'drink', 'spoke' => 'speak', 'knew' => 'know', 'thought' => 'think', 'slept' => 'sleep', 'woke' => 'wake',
        'swam' => 'swim', 'ran' => 'run', 'met' => 'meet', 'felt' => 'feel', 'left' => 'leave', 'gave' => 'give', 'found' => 'find',
        'played' => 'play', 'watched' => 'watch', 'cooked' => 'cook', 'visited' => 'visit', 'studied' => 'study', 'worked' => 'work',
        'liked' => 'like', 'wanted' => 'want', 'walked' => 'walk', 'stayed' => 'stay', 'finished' => 'finish', 'listened' => 'listen',
        'enjoyed' => 'enjoy', 'tried' => 'try', 'used' => 'use', 'lived' => 'live', 'loved' => 'love', 'helped' => 'help'];

    private const UNCOUNTABLE = ['informations' => 'information', 'advices' => 'advice', 'homeworks' => 'homework',
        'furnitures' => 'furniture', 'equipments' => 'equipment', 'knowledges' => 'knowledge', 'luggages' => 'luggage',
        'feedbacks' => 'feedback', 'researches' => 'research'];

    /** @var list<array{original: string, corrected: string, explanation: string, type: string}> */
    private array $corrections = [];

    public function respond(array $topic, string $levelCode, array $history, string $transcript): ?CoachResult
    {
        $text = $this->correct(trim((string) preg_replace('/\s+/', ' ', $transcript)));
        $words = str_word_count($transcript);

        $score = 100 - 12 * count($this->corrections);
        if ($words < 5) {
            $score = min($score, 60);
        } elseif ($words < 10) {
            $score = min($score, 85);
        }
        $score = max(20, $score);

        if ($this->corrections !== []) {
            $tip = 'Perhatikan: '.$this->corrections[0]['explanation'];
        } elseif ($words < 5) {
            $tip = 'Jawabanmu masih sangat pendek. Coba jawab dengan 2–3 kalimat lengkap (subjek + kata kerja).';
        } elseif ($words < 10) {
            $tip = 'Tidak ada kesalahan umum yang terdeteksi. Coba kembangkan jawaban dengan alasan: "because…" atau contoh: "for example…".';
        } else {
            $tip = 'Bagus! Tidak ada kesalahan umum yang terdeteksi. Pertahankan dan coba pakai kosakata yang lebih bervariasi.';
        }

        $followups = $topic['followups'];
        $next = $followups[count($history) % count($followups)];
        $ack = match (true) {
            $score >= 90 => 'Great answer!',
            $score >= 70 => 'Nice, thanks for sharing.',
            default => 'Good try!',
        };

        return new CoachResult($text, $score, $this->corrections, $tip, $ack.' '.$next, 'rules');
    }

    private function correct(string $text): string
    {
        $this->corrections = [];
        $text = $this->rule($text, '/\bi\b/', fn () => 'I',
            '"I" (saya) selalu ditulis dengan huruf kapital.', 'grammar');

        $age = '(\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|[a-z]+teen|(?:twenty|thirty|forty|fifty|sixty|seventy|eighty)(?:[- ][a-z]+)?)';
        $text = $this->rule($text, '/\b(I|you|we|they) (have|has) '.$age.'( years?)? old\b/i',
            fn ($m) => $m[1].' '.($this->beFor($m[1])).' '.$m[3].($m[4] ?? '').' old',
            'Untuk umur pakai "to be", bukan "have": "I am 20 years old" (bukan "I have 20 years old").', 'grammar');

        $text = $this->rule($text, '/\b(he|she|it|my (?:mother|father|mom|dad|brother|sister|friend)) (have|has) '.$age.'( years?)? old\b/i',
            fn ($m) => $m[1].' is '.$m[3].($m[4] ?? '').' old',
            'Untuk umur pakai "to be": "She is 10 years old".', 'grammar');

        $text = $this->rule($text, '/\bI (is|are)\b/', fn () => 'I am',
            'Subjek "I" memakai "am": "I am…".', 'grammar');
        $text = $this->rule($text, '/\b(he|she|it) (are|am)\b/i', fn ($m) => $m[1].' is',
            'Subjek he/she/it memakai "is".', 'grammar');
        $text = $this->rule($text, '/\b(you|we|they|people) (is|am)\b/i', fn ($m) => $m[1].' are',
            'Subjek you/we/they (dan "people") memakai "are".', 'grammar');

        $text = $this->rule($text, '/\bI am (agree|disagree|like|want|need|think|know|go|live|work|study)\b/i', fn ($m) => 'I '.$m[1],
            'Jangan gabungkan "am" dengan kata kerja biasa: "I agree", bukan "I am agree".', 'grammar');

        $text = $this->rule($text, '/\b(he|she|it) don\'?t\b/i', fn ($m) => $m[1].' doesn\'t',
            'He/she/it memakai "doesn\'t", bukan "don\'t".', 'grammar');

        $verbs = implode('|', self::VERBS);
        $text = $this->rule($text,
            '/(?<!\bcan |\bwill |\bdoes |\bdid |\bto |\bshould |\bwould |\bcould |\bmust |\bmay |\bmight |\blet |\bmake |\bhelp |\bdoesn\'t |\bdidn\'t |\bcan\'t |\bwon\'t )\b(he|she|it|my (?:mother|father|mom|dad|brother|sister|friend|teacher|wife|husband))\s+(?:usually |always |often |never |sometimes |also |really )?('.$verbs.')\b/i',
            function ($m) {
                $adverb = trim(substr($m[0], strlen($m[1]), -strlen($m[2])));

                return $m[1].' '.($adverb !== '' ? $adverb.' ' : '').$this->thirdPerson($m[2]);
            },
            'Present simple untuk he/she/it (orang ketiga tunggal): kata kerja ditambah -s/-es, mis. "she goes", "he has".', 'grammar');

        $past = implode('|', array_keys(self::PAST_TO_BASE));
        $text = $this->rule($text, '/\b(didn\'t|did not|did you|did he|did she|did they|did we) ('.$past.')\b/i',
            fn ($m) => $m[1].' '.self::PAST_TO_BASE[strtolower($m[2])],
            'Setelah "did/didn\'t" pakai kata kerja bentuk dasar: "I didn\'t go", bukan "I didn\'t went".', 'grammar');

        $text = $this->rule($text, '/\b(can|must|should|will|could|would|might|may) to (\w+)/i', fn ($m) => $m[1].' '.$m[2],
            'Setelah modal (can, must, should, will…) langsung kata kerja dasar tanpa "to".', 'grammar');

        $text = $this->rule($text, '/\b(more|most) (better|best|bigger|biggest|smaller|easier|happier|faster|cheaper|worse|worst)\b/i',
            fn ($m) => $m[2], 'Jangan pakai "more/most" dengan bentuk -er/-est atau better/best.', 'grammar');

        $text = $this->rule($text, '/\bI very (like|love|enjoy|want|hate)\b/i', fn ($m) => 'I really '.$m[1],
            '"Very" tidak dipakai sebelum kata kerja. Pakai "I really like…" atau "I like… very much".', 'word_choice');

        $text = $this->rule($text, '/\b(discuss|explain|mention) about\b/i', fn ($m) => $m[1],
            '"Discuss/explain/mention" langsung diikuti objek, tanpa "about".', 'word_choice');

        $text = $this->rule($text, '/\b(many )?('.implode('|', array_keys(self::UNCOUNTABLE)).')\b/i',
            fn ($m) => ($m[1] !== '' ? 'a lot of ' : '').self::UNCOUNTABLE[strtolower($m[2])],
            'Kata ini tidak bisa dihitung (uncountable), jadi tidak diberi -s.', 'vocabulary');

        $text = $this->rule($text, '/\ban (?!hour|honest|honou?r|heir|[aeiou])([a-z]+)/i', fn ($m) => 'a '.$m[1],
            'Pakai "a" sebelum bunyi konsonan: "a book", "a university".', 'grammar');
        $text = $this->rule($text, '/\ba (?!uni|use|usu|eu|one|once|u[rt]\w)([aeiou][a-z]*|hour|honest)\b/i', fn ($m) => 'an '.$m[1],
            'Pakai "an" sebelum bunyi vokal: "an apple", "an hour".', 'grammar');

        return $text;
    }

    /** Applies one regex rule, recording each distinct change as a correction. */
    private function rule(string $text, string $pattern, callable $replace, string $explanation, string $type): string
    {
        return (string) preg_replace_callback($pattern, function (array $m) use ($replace, $explanation, $type) {
            $new = (string) $replace($m);
            if ($new !== $m[0]) {
                $new = $this->matchCase($m[0], $new);
                $this->corrections[] = ['original' => $m[0], 'corrected' => $new, 'explanation' => $explanation, 'type' => $type];
            }

            return $new;
        }, $text);
    }

    private function matchCase(string $original, string $new): string
    {
        if ($original !== '' && ctype_upper($original[0]) && ! str_starts_with($new, 'I ') && $new !== 'I') {
            return ucfirst($new);
        }

        return $new;
    }

    private function beFor(string $subject): string
    {
        return strtolower($subject) === 'i' ? 'am' : 'are';
    }

    private function thirdPerson(string $verb): string
    {
        $v = strtolower($verb);

        return match (true) {
            $v === 'have' => 'has',
            $v === 'do' || $v === 'go' => $v.'es',
            (bool) preg_match('/(s|sh|ch|x|z)$/', $v) => $v.'es',
            (bool) preg_match('/[^aeiou]y$/', $v) => substr($v, 0, -1).'ies',
            default => $v.'s',
        };
    }
}
