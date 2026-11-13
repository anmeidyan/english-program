<?php

declare(strict_types=1);

namespace App\Services\Speaking;

interface SpeakingCoachInterface
{
    /**
     * Correct what the learner just said and keep the conversation going.
     *
     * @param  array{key: string, band: string, title: string, title_id: string, opener: string, followups: list<string>}  $topic
     * @param  list<array{learner: string, coach: string}>  $history  earlier turns of this session, oldest first
     * @return CoachResult|null null = this coach could not answer (caller falls back)
     */
    public function respond(array $topic, string $levelCode, array $history, string $transcript): ?CoachResult;
}
