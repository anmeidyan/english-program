<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpeakingTurnRequest;
use App\Models\SpeakingSession;
use App\Models\SpeakingTurn;
use App\Services\Speaking\SpeakingService;
use App\Services\Speaking\SpeakingTopics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Daily speaking practice: talk about the topic of the day and get each sentence corrected. */
class SpeakingController extends Controller
{
    public function __construct(private readonly SpeakingService $speaking) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $session = $this->speaking->currentSession($user);

        return view('learner.speaking', [
            'state' => $this->state($request, $session),
            'history' => $this->speaking->history($user),
            'streak' => $this->speaking->speakingStreak($user),
        ]);
    }

    public function turn(SpeakingTurnRequest $request): JsonResponse
    {
        $user = $request->user();
        $session = SpeakingSession::query()->whereKey($request->integer('session_id'))->where('user_id', $user->id)->first();
        abort_if($session === null, 404);
        abort_unless($session->practice_date->toDateString() === $this->speaking->today($user), 409, __('Sesi ini sudah lewat. Muat ulang halaman untuk topik hari ini.'));
        abort_if($session->turns_count >= (int) config('englishpath.speaking.max_turns_per_session', 30), 422, __('Sesi ini sudah mencapai batas. Coba ganti topik.'));

        $out = $this->speaking->submitTurn($user, $session, trim((string) $request->validated('transcript')));

        return response()->json([
            'turn' => $out['turn']->toPayload(),
            'today_turns' => $out['today_turns'],
            'goal_reached_now' => $out['goal_reached_now'],
            'xp' => $out['goal_reached_now'] ? (int) config('englishpath.xp.speaking_daily', 0) : 0,
        ]);
    }

    public function changeTopic(Request $request): JsonResponse
    {
        $session = $this->speaking->changeTopic($request->user());

        return response()->json($this->state($request, $session));
    }

    /** @return array<string, mixed> */
    private function state(Request $request, SpeakingSession $session): array
    {
        $topic = SpeakingTopics::find($session->topic_key) ?? SpeakingTopics::all()[0];

        return [
            'session_id' => $session->id,
            'topic' => ['key' => $topic['key'], 'title' => $topic['title'], 'title_id' => $topic['title_id'], 'opener' => $topic['opener']],
            'level' => $session->level_code,
            'turns' => $session->turns()->get()->map(fn (SpeakingTurn $t) => $t->toPayload())->all(),
            'today_turns' => $this->speaking->todayTurns($request->user()),
            'goal' => (int) config('englishpath.speaking.daily_goal_turns', 5),
            'xp' => (int) config('englishpath.xp.speaking_daily', 0),
        ];
    }
}
