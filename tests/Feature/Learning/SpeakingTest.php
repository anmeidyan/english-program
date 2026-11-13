<?php

declare(strict_types=1);

use App\Models\SpeakingSession;
use App\Models\XpLog;
use App\Services\Speaking\CoachResult;
use App\Services\Speaking\SpeakingCoachInterface;

beforeEach(function () {
    config(['englishpath.speaking.daily_goal_turns' => 3, 'englishpath.xp.speaking_daily' => 15]);
    $this->user = student();
});

function speakTurn($test, $user, string $transcript)
{
    $session = SpeakingSession::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

    return $test->actingAs($user)->postJson(route('api.speaking.turn'), ['session_id' => $session->id, 'transcript' => $transcript]);
}

it('shows the topic of the day and opens a session', function () {
    $this->actingAs($this->user)->get(route('speaking'))->assertOk()->assertSee('Latihan Speaking Harian');

    expect(SpeakingSession::query()->where('user_id', $this->user->id)->count())->toBe(1);

    // visiting again keeps the same session
    $this->actingAs($this->user)->get(route('speaking'))->assertOk();
    expect(SpeakingSession::query()->where('user_id', $this->user->id)->count())->toBe(1);
});

it('corrects what the learner said and replies with a follow-up question', function () {
    $this->actingAs($this->user)->get(route('speaking'));

    speakTurn($this, $this->user, 'I have 20 years old and my mother work in a hospital')
        ->assertOk()
        ->assertJsonPath('turn.corrected', 'I am 20 years old and my mother works in a hospital')
        ->assertJsonCount(2, 'turn.corrections')
        ->assertJsonPath('today_turns', 1)
        ->assertJsonPath('goal_reached_now', false)
        ->assertJson(fn ($json) => $json->whereType('turn.reply', 'string')->etc());

    $session = SpeakingSession::query()->where('user_id', $this->user->id)->first();
    expect($session->turns_count)->toBe(1)->and($session->turns()->first()->coach)->toBe('rules');
});

it('pays XP and counts the streak once when the daily goal is reached', function () {
    $this->actingAs($this->user)->get(route('speaking'));

    speakTurn($this, $this->user, 'My name is Rina.')->assertJsonPath('goal_reached_now', false);
    speakTurn($this, $this->user, 'I live in Bandung.')->assertJsonPath('goal_reached_now', false);
    speakTurn($this, $this->user, 'I like reading books.')->assertJsonPath('goal_reached_now', true)->assertJsonPath('xp', 15);
    speakTurn($this, $this->user, 'I also like music.')->assertJsonPath('goal_reached_now', false);

    $user = $this->user->fresh();
    expect($user->xp_total)->toBe(15)
        ->and($user->streak_current)->toBe(1)
        ->and(XpLog::query()->where('user_id', $user->id)->where('source_type', 'speaking_daily')->count())->toBe(1);
});

it('counts turns across topics toward the daily goal', function () {
    $this->actingAs($this->user)->get(route('speaking'));
    speakTurn($this, $this->user, 'My name is Rina.');
    speakTurn($this, $this->user, 'I live in Bandung.');

    $first = SpeakingSession::query()->where('user_id', $this->user->id)->first();
    $this->actingAs($this->user)->postJson(route('api.speaking.topic'))
        ->assertOk()
        ->assertJsonPath('today_turns', 2)
        ->assertJsonCount(0, 'turns')
        ->assertJson(fn ($json) => $json->where('session_id', fn ($id) => $id !== $first->id)->etc());

    speakTurn($this, $this->user, 'I like reading books.')->assertJsonPath('goal_reached_now', true);
});

it('rejects other learners\' sessions, empty turns and stale sessions', function () {
    $this->actingAs($this->user)->get(route('speaking'));
    $session = SpeakingSession::query()->where('user_id', $this->user->id)->first();

    $this->actingAs(student())->postJson(route('api.speaking.turn'), ['session_id' => $session->id, 'transcript' => 'Hello there'])->assertNotFound();
    $this->actingAs($this->user)->postJson(route('api.speaking.turn'), ['session_id' => $session->id, 'transcript' => ''])->assertUnprocessable();

    $session->update(['practice_date' => now()->subDays(2)->toDateString()]);
    $this->actingAs($this->user)->postJson(route('api.speaking.turn'), ['session_id' => $session->id, 'transcript' => 'Hello there'])->assertStatus(409);
});

it('falls back to the offline coach when the AI coach cannot answer', function () {
    app()->bind(SpeakingCoachInterface::class, fn () => new class implements SpeakingCoachInterface
    {
        public function respond(array $topic, string $levelCode, array $history, string $transcript): ?CoachResult
        {
            return null;
        }
    });
    $this->actingAs($this->user)->get(route('speaking'));

    speakTurn($this, $this->user, 'She don\'t like coffee')->assertOk()->assertJsonPath('turn.corrected', 'She doesn\'t like coffee');
});

it('uses the AI coach result and passes the conversation history', function () {
    $fake = new class implements SpeakingCoachInterface
    {
        public array $calls = [];

        public function respond(array $topic, string $levelCode, array $history, string $transcript): ?CoachResult
        {
            $this->calls[] = $history;

            return new CoachResult('I went to the beach yesterday.', 80, [
                ['original' => 'go', 'corrected' => 'went', 'explanation' => 'Pakai past tense untuk kemarin.', 'type' => 'grammar'],
            ], 'Pakai past tense.', 'Sounds fun! Who did you go with?', 'claude:test');
        }
    };
    app()->instance(SpeakingCoachInterface::class, $fake);
    $this->actingAs($this->user)->get(route('speaking'));

    speakTurn($this, $this->user, 'I go to the beach yesterday')->assertOk()->assertJsonPath('turn.reply', 'Sounds fun! Who did you go with?');
    speakTurn($this, $this->user, 'With my friends')->assertOk();

    expect($fake->calls[0])->toBe([])
        ->and($fake->calls[1])->toBe([['learner' => 'I go to the beach yesterday', 'coach' => 'Sounds fun! Who did you go with?']]);
});

it('links the practice from the dashboard', function () {
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee(route('speaking'));
});
