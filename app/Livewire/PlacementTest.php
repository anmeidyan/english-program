<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Question;
use App\Services\Grading\QuestionPresenter;
use App\Services\PlacementService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Adaptive placement test (PRD 3.4). Question ids, stage and scores are locked server-side
 * properties; the answer key is never rendered.
 */
#[Layout('layouts.app')]
class PlacementTest extends Component
{
    #[Locked]
    public string $stage = PlacementService::START;

    /** @var list<int> */
    #[Locked]
    public array $queue = [];

    /** @var list<int> */
    #[Locked]
    public array $asked = [];

    /** @var array<string, int> */
    #[Locked]
    public array $scores = [];

    #[Locked]
    public int $correctInStage = 0;

    #[Locked]
    public bool $started = false;

    #[Locked]
    public ?string $resultLevel = null;

    /** fixed for the whole test so options don't jump between re-renders */
    #[Locked]
    public int $seed = 0;

    public ?string $answer = null;

    public ?string $blockedUntil = null;

    public function mount(PlacementService $placement): void
    {
        $until = $placement->nextAllowedAt(auth()->user());
        $this->blockedUntil = $until?->translatedFormat('d F Y');
    }

    public function start(PlacementService $placement): void
    {
        abort_if($this->blockedUntil !== null, 403);
        $this->started = true;
        $this->seed = random_int(1, 1_000_000_000);
        $this->loadStage($placement, $this->stage);
    }

    public function submit(PlacementService $placement): void
    {
        $this->validate(['answer' => ['required', 'string', 'max:20']]);
        $id = array_shift($this->queue);
        if ($id === null) {
            return;
        }
        $question = Question::query()->findOrFail($id);
        if ($placement->isCorrect($question, $this->answer)) {
            $this->correctInStage++;
        }
        $this->answer = null;

        if ($this->queue === []) {
            $this->scores[$this->stage] = $this->correctInStage;
            $decision = $placement->advance($this->stage, $this->correctInStage, $this->scores);
            if ($decision['done'] || $decision['next'] === null) {
                $this->complete($placement, (string) $decision['recommended']);

                return;
            }
            $this->loadStage($placement, $decision['next']);
        }
    }

    public function skip(PlacementService $placement): void
    {
        $this->answer = '__skip__';
        $this->submit($placement);
    }

    private function loadStage(PlacementService $placement, string $code): void
    {
        $this->stage = $code;
        $this->correctInStage = 0;
        $this->queue = $placement->stageQuestions($code, $this->asked);
        $this->asked = [...$this->asked, ...$this->queue];
        if ($this->queue === []) {
            // no items for this level: treat as the boundary
            $this->complete($placement, $code);
        }
    }

    private function complete(PlacementService $placement, string $recommended): void
    {
        $result = $placement->finish(auth()->user(), $recommended, $this->scores);
        $this->resultLevel = $result->recommendedLevel->label();
        $this->queue = [];
    }

    public function render(QuestionPresenter $presenter): View
    {
        $current = $this->queue !== [] ? Question::query()->find($this->queue[0]) : null;

        return view('livewire.placement-test', [
            'question' => $current ? $presenter->present($current, QuestionPresenter::MODE_EXAM, $this->seed) : null,
            'progress' => count($this->asked) - count($this->queue),
        ]);
    }
}
