<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Progress\UnlockService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** PRD 10.1 onboarding: goal, daily target, reminder, then placement test or start from the beginning. */
#[Layout('layouts.app')]
class Onboarding extends Component
{
    public int $step = 1;

    public string $goal = 'conversation';

    public int $dailyGoal = 20;

    public string $reminderTime = '19:00';

    public string $timezone = 'Asia/Jakarta';

    public string $locale = 'id';

    /** @var array<string, string> */
    public array $goals = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->goals = [
            'conversation' => __('Lancar bercakap sehari-hari'),
            'work' => __('Karier & pekerjaan'),
            'study' => __('Studi ke luar negeri / IELTS / TOEFL'),
            'travel' => __('Traveling'),
            'school' => __('Sekolah / kuliah'),
        ];
        $this->dailyGoal = $user->daily_goal_minutes ?: 20;
        $this->timezone = $user->timezone ?: 'Asia/Jakarta';
        $this->locale = $user->locale ?: 'id';
    }

    public function next(): void
    {
        $this->validate($this->rulesFor($this->step));
        $this->step = min(3, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function finish(string $mode, UnlockService $unlock): void
    {
        $this->validate($this->rulesFor(1) + $this->rulesFor(2));
        $user = auth()->user();
        $user->forceFill([
            'learning_goal' => $this->goal,
            'daily_goal_minutes' => $this->dailyGoal,
            'reminder_time' => $this->reminderTime,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'onboarded_at' => now(),
        ])->save();

        if ($mode === 'placement') {
            $this->redirectRoute('placement', navigate: false);

            return;
        }

        $unlock->initialize($user);
        $this->redirectRoute('dashboard', navigate: false);
    }

    /** @return array<string, mixed> */
    private function rulesFor(int $step): array
    {
        return match ($step) {
            1 => ['goal' => ['required', Rule::in(['conversation', 'work', 'study', 'travel', 'school'])]],
            2 => [
                'dailyGoal' => ['required', Rule::in([10, 20, 30, 60])],
                'reminderTime' => ['required', 'date_format:H:i'],
                'timezone' => ['required', 'timezone:all'],
                'locale' => ['required', Rule::in(['id', 'en'])],
            ],
            default => [],
        };
    }

    public function render(): View
    {
        return view('livewire.onboarding');
    }
}
