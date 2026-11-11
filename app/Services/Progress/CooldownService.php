<?php

declare(strict_types=1);

namespace App\Services\Progress;

use App\Models\Exam;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;

/**
 * PRD 5.3: cooldown after the n-th failure. The rules list holds minutes per failure;
 * the last value repeats (unit: 1h, 6h, 24h, 24h…; level: 24h, 72h, 72h…).
 */
class CooldownService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function minutesAfterFailure(Exam $exam, int $failCount): int
    {
        /** @var list<int> $rules */
        $rules = $exam->cooldown_rules ?: (array) $this->settings->get('cooldown.'.$exam->type->value, [0]);
        if ($rules === [] || $failCount < 1) {
            return 0;
        }

        return (int) ($rules[min($failCount, count($rules)) - 1]);
    }

    public function until(Exam $exam, int $failCount, ?Carbon $from = null): ?Carbon
    {
        $minutes = $this->minutesAfterFailure($exam, $failCount);

        return $minutes > 0 ? ($from ?? now())->copy()->addMinutes($minutes) : null;
    }
}
