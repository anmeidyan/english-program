<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/** Global settings (PRD 7.1 Settings) stored in the `settings` table, cached, with code defaults. */
class SettingsService
{
    private const CACHE_KEY = 'settings:all';

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'passing_score.lesson_quiz' => 80,
        'passing_score.unit_exam' => 75,
        'passing_score.level_exam' => 75,
        'passing_score.tenses_checkpoint' => 85,
        'min_skill_score.unit_exam' => 60,
        'min_skill_score.level_exam' => 65,
        'cooldown.lesson_quiz' => [0],
        'cooldown.unit_exam' => [60, 360, 1440],
        'cooldown.level_exam' => [1440, 4320],
        'cooldown.tenses_checkpoint' => [0],
        'review.sla_hours' => 48,
        'ai_grader.enabled' => false,
        'placement.retake_days' => 30,
        'audio.max_plays_default' => 2,
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        /** @var array<string, mixed> $stored */
        $stored = Cache::remember(self::CACHE_KEY, 3600, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, $stored);
    }

    public function aiGraderEnabled(): bool
    {
        return (bool) $this->get('ai_grader.enabled') && config('englishpath.ai_grader.driver') !== 'null';
    }
}
