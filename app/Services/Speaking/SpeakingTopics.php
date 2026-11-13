<?php

declare(strict_types=1);

namespace App\Services\Speaking;

use App\Enums\LevelCode;

/**
 * Daily speaking topics grouped by CEFR band. Each topic has an opener and follow-up
 * questions; the offline coach walks through the follow-ups, Claude improvises its own.
 *
 * @phpstan-type Topic array{key: string, band: string, title: string, title_id: string, opener: string, followups: list<string>}
 */
final class SpeakingTopics
{
    /** @return list<Topic> */
    public static function all(): array
    {
        return [
            // basic: Pre-A1 – A2
            self::t('introduce-yourself', 'basic', 'Introduce yourself', 'Perkenalan diri', 'Hi! Can you tell me about yourself? What is your name and where are you from?', [
                'How old are you, and what do you do — are you a student or do you work?',
                'What do you like to do in your free time?',
                'Tell me about your family. How many people are there?',
                'Why do you want to learn English?',
            ]),
            self::t('daily-routine', 'basic', 'My daily routine', 'Rutinitas harian', 'What do you usually do in the morning?', [
                'What time do you usually go to bed?',
                'What do you usually eat for lunch?',
                'What do you do after school or work?',
                'What is your favourite part of the day? Why?',
            ]),
            self::t('food', 'basic', 'Food I like', 'Makanan favorit', 'What is your favourite food?', [
                'Can you cook? What can you make?',
                'What did you eat yesterday?',
                'Is there a food you don\'t like? Why?',
                'What is a famous food from your city?',
            ]),
            self::t('weekend', 'basic', 'My weekend', 'Akhir pekan', 'What did you do last weekend?', [
                'Who did you spend the weekend with?',
                'What are you going to do next weekend?',
                'Do you prefer to stay at home or go out on weekends?',
                'Tell me about a really fun weekend you had.',
            ]),
            self::t('my-home', 'basic', 'My home', 'Rumahku', 'Can you describe your house or apartment?', [
                'What is your favourite room? Why?',
                'What can you see from your window?',
                'Who do you live with?',
                'What would you like to change in your home?',
            ]),
            self::t('hobbies', 'basic', 'Hobbies', 'Hobi', 'What hobbies do you have?', [
                'How often do you do it?',
                'When did you start this hobby?',
                'Is there a new hobby you want to try?',
                'Do your friends have the same hobbies as you?',
            ]),
            self::t('shopping', 'basic', 'Shopping', 'Belanja', 'Do you like shopping? Where do you usually go?', [
                'What was the last thing you bought?',
                'Do you prefer shopping online or in a store? Why?',
                'How much money do you usually spend in a week?',
                'Tell me about a gift you bought for someone.',
            ]),

            // intermediate: B1 – B2
            self::t('travel', 'intermediate', 'A trip to remember', 'Perjalanan berkesan', 'Tell me about the best trip you have ever taken.', [
                'What made that trip so special for you?',
                'Did anything go wrong during the trip? How did you deal with it?',
                'If you could travel anywhere next year, where would you go and why?',
                'Do you prefer travelling alone or with other people?',
            ]),
            self::t('work-study', 'intermediate', 'Work and study', 'Kerja dan kuliah', 'What are you working on or studying at the moment?', [
                'What do you find most challenging about it?',
                'How do you stay motivated when things get difficult?',
                'Where do you see yourself in five years?',
                'What advice would you give someone starting in your field?',
            ]),
            self::t('technology', 'intermediate', 'Technology in my life', 'Teknologi sehari-hari', 'How much time do you spend on your phone every day?', [
                'Which app could you not live without? Why?',
                'Do you think social media has a good or bad effect on people?',
                'How has technology changed the way you study or work?',
                'What technology do you think will change our lives in the next ten years?',
            ]),
            self::t('health', 'intermediate', 'Staying healthy', 'Hidup sehat', 'What do you do to stay healthy?', [
                'Have you ever tried to change a bad habit? What happened?',
                'Do you think people today are healthier than in the past?',
                'How do you deal with stress?',
                'What is one healthy habit you would like to start?',
            ]),
            self::t('city-life', 'intermediate', 'City or village', 'Kota atau desa', 'Would you rather live in a big city or in a small village? Why?', [
                'What are the biggest problems in your city?',
                'If you were the mayor, what would you change first?',
                'How has your hometown changed since you were a child?',
                'Describe a place in your city that you would recommend to a tourist.',
            ]),
            self::t('movies-books', 'intermediate', 'Films and books', 'Film dan buku', 'What is a film or book that you really enjoyed recently?', [
                'What was it about, without giving away the ending?',
                'Who would you recommend it to, and why?',
                'Do you prefer watching the film or reading the book?',
                'Is there a story that changed the way you think?',
            ]),

            // advanced: C1 – C2
            self::t('education', 'advanced', 'The future of education', 'Masa depan pendidikan', 'Should schools focus more on practical skills than on academic knowledge?', [
                'What role should AI play in the classroom?',
                'How would you redesign the exam system in your country?',
                'Is a university degree still worth the cost?',
                'What is something you wish you had been taught at school?',
            ]),
            self::t('environment', 'advanced', 'Climate and responsibility', 'Iklim dan tanggung jawab', 'Who should take the most responsibility for climate change: individuals, companies or governments?', [
                'Are individual actions like recycling actually meaningful?',
                'How should developing countries balance growth and sustainability?',
                'What environmental change have you noticed where you live?',
                'Would you accept higher prices for greener products?',
            ]),
            self::t('work-culture', 'advanced', 'Work culture', 'Budaya kerja', 'Is remote work better for employees and companies in the long run?', [
                'What makes a manager genuinely good at their job?',
                'Should companies be allowed to monitor their employees\' activity?',
                'How do you define success in a career?',
                'Would a four-day work week work in your country?',
            ]),
            self::t('media', 'advanced', 'News and media', 'Berita dan media', 'How do you decide whether a news story is trustworthy?', [
                'Has social media made public debate better or worse?',
                'Should governments regulate online platforms more strictly?',
                'Describe a time you changed your mind because of something you read.',
                'What responsibility do journalists have in a crisis?',
            ]),
        ];
    }

    public static function band(?LevelCode $level): string
    {
        return match ($level) {
            LevelCode::B1, LevelCode::B2 => 'intermediate',
            LevelCode::C1, LevelCode::C2 => 'advanced',
            default => 'basic',
        };
    }

    /** @return list<Topic> */
    public static function forBand(string $band): array
    {
        return array_values(array_filter(self::all(), fn (array $t) => $t['band'] === $band));
    }

    /** @return Topic|null */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $topic) {
            if ($topic['key'] === $key) {
                return $topic;
            }
        }

        return null;
    }

    /**
     * Same topic for everyone at a band on a given day; $offset picks "another topic".
     *
     * @return Topic
     */
    public static function forDay(string $band, string $ymd, int $offset = 0): array
    {
        $topics = self::forBand($band);
        $index = (crc32($band.$ymd) + $offset) % count($topics);

        return $topics[$index];
    }

    /**
     * @param  list<string>  $followups
     * @return Topic
     */
    private static function t(string $key, string $band, string $title, string $titleId, string $opener, array $followups): array
    {
        return ['key' => $key, 'band' => $band, 'title' => $title, 'title_id' => $titleId, 'opener' => $opener, 'followups' => $followups];
    }
}
