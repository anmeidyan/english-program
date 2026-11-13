<?php

declare(strict_types=1);

namespace App\Services\Speaking;

use App\Models\SpeakingSession;
use App\Models\SpeakingTurn;
use App\Models\User;
use App\Services\Gamification\StreakService;
use App\Services\Gamification\XpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily speaking practice: one topic per day (per CEFR band), the learner talks, the coach corrects
 * each turn. Reaching the daily goal (englishpath.speaking.daily_goal_turns) pays XP once per day
 * and counts as an active day for the streak.
 */
class SpeakingService
{
    public function __construct(
        private readonly SpeakingCoachInterface $coach,
        private readonly RuleBasedSpeakingCoach $fallback,
        private readonly XpService $xp,
        private readonly StreakService $streak,
    ) {}

    public function today(User $user): string
    {
        return now($user->timezone)->toDateString();
    }

    public function levelCode(User $user): string
    {
        return $user->currentLevel?->code->value ?? 'A1';
    }

    /** Today's latest session, or a new one on the topic of the day. */
    public function currentSession(User $user): SpeakingSession
    {
        $today = $this->today($user);
        $latest = SpeakingSession::query()->where('user_id', $user->id)->whereDate('practice_date', $today)->latest('id')->first();

        return $latest ?? $this->start($user, 0);
    }

    /** Switch to another topic for today (keeps earlier sessions and their turns). */
    public function changeTopic(User $user): SpeakingSession
    {
        $today = $this->today($user);
        $used = SpeakingSession::query()->where('user_id', $user->id)->whereDate('practice_date', $today)->pluck('topic_key')->all();
        $band = SpeakingTopics::band($user->currentLevel?->code);
        $count = count(SpeakingTopics::forBand($band));

        for ($offset = 1; $offset <= $count; $offset++) {
            $topic = SpeakingTopics::forDay($band, $today, $offset);
            if (! in_array($topic['key'], $used, true)) {
                return $this->start($user, $offset);
            }
        }

        return $this->currentSession($user);
    }

    /** @return array{session: SpeakingSession, turn: SpeakingTurn, today_turns: int, goal_reached_now: bool} */
    public function submitTurn(User $user, SpeakingSession $session, string $transcript): array
    {
        $topic = SpeakingTopics::find($session->topic_key) ?? SpeakingTopics::all()[0];
        $history = $session->turns()->get()->map(fn (SpeakingTurn $t) => ['learner' => $t->transcript, 'coach' => $t->reply])->all();

        $result = $this->coach->respond($topic, $session->level_code, $history, $transcript)
            ?? $this->fallback->respond($topic, $session->level_code, $history, $transcript);
        assert($result !== null);

        $goal = (int) config('englishpath.speaking.daily_goal_turns', 5);
        $before = $this->todayTurns($user);

        $turn = DB::transaction(function () use ($session, $transcript, $result) {
            $turn = $session->turns()->create([
                'transcript' => $transcript,
                'corrected' => $result->corrected,
                'score' => $result->score,
                'corrections' => $result->corrections,
                'tip' => $result->tip,
                'reply' => $result->reply,
                'coach' => $result->coach,
            ]);
            $session->forceFill([
                'turns_count' => $session->turns()->count(),
                'avg_score' => round((float) $session->turns()->avg('score'), 2),
            ])->save();

            return $turn;
        });

        $after = $before + 1;
        $reachedNow = $before < $goal && $after >= $goal;
        if ($reachedNow) {
            $this->xp->awardConfigured($user, 'speaking_daily', (int) str_replace('-', '', $this->today($user)));
            $this->streak->recordActivity($user);
        }

        return ['session' => $session, 'turn' => $turn, 'today_turns' => $after, 'goal_reached_now' => $reachedNow];
    }

    public function todayTurns(User $user): int
    {
        return (int) SpeakingSession::query()->where('user_id', $user->id)
            ->whereDate('practice_date', $this->today($user))->sum('turns_count');
    }

    /**
     * Last $days days (oldest first) with turns and average score, for the practice calendar.
     *
     * @return list<array{date: string, turns: int, score: float|null, goal: bool}>
     */
    public function history(User $user, int $days = 14): array
    {
        $goal = (int) config('englishpath.speaking.daily_goal_turns', 5);
        $today = Carbon::parse($this->today($user));
        $from = $today->copy()->subDays($days - 1);

        $rows = SpeakingSession::query()->where('user_id', $user->id)
            ->whereDate('practice_date', '>=', $from->toDateString())
            ->get()->groupBy(fn (SpeakingSession $s) => $s->practice_date->toDateString());

        $out = [];
        for ($d = $from->copy(); $d->lessThanOrEqualTo($today); $d->addDay()) {
            $group = $rows->get($d->toDateString());
            $turns = $group ? (int) $group->sum('turns_count') : 0;
            $scored = $group ? $group->filter(fn ($s) => $s->avg_score !== null) : collect();
            $score = $scored->isNotEmpty()
                ? round($scored->sum(fn ($s) => (float) $s->avg_score * $s->turns_count) / max(1, $scored->sum('turns_count')), 1)
                : null;
            $out[] = ['date' => $d->toDateString(), 'turns' => $turns, 'score' => $score, 'goal' => $turns >= $goal];
        }

        return $out;
    }

    /** Consecutive days (ending today, or yesterday if today isn't done yet) that reached the goal. */
    public function speakingStreak(User $user): int
    {
        $days = array_reverse($this->history($user, 60));
        if ($days !== [] && ! $days[0]['goal']) {
            array_shift($days);
        }
        $streak = 0;
        foreach ($days as $day) {
            if (! $day['goal']) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    private function start(User $user, int $offset): SpeakingSession
    {
        $today = $this->today($user);
        $topic = SpeakingTopics::forDay(SpeakingTopics::band($user->currentLevel?->code), $today, $offset);

        return SpeakingSession::query()->firstOrCreate(
            ['user_id' => $user->id, 'practice_date' => $today, 'topic_key' => $topic['key']],
            ['level_code' => $this->levelCode($user)],
        );
    }
}
