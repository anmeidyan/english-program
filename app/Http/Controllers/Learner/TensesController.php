<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\TenseTopic;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Services\Progress\UnlockService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tenses Master path (PRD 3.3). */
class TensesController extends Controller
{
    public function __construct(
        private readonly UnlockService $unlock,
        private readonly ProgressRepositoryInterface $progress,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load('currentLevel');
        $topics = TenseTopic::query()->published()->with('requiredLevel', 'exam')->orderBy('sort_order')->get();
        $rows = $this->progress->forItems($user, 'tense_topic', $topics->pluck('id')->all());

        return view('learner.tenses', [
            'topics' => $topics,
            'rows' => $rows,
            'open' => $topics->mapWithKeys(fn (TenseTopic $t) => [$t->id => $this->unlock->tenseTopicOpen($user, $t)]),
        ]);
    }

    public function show(Request $request, TenseTopic $topic): View
    {
        $user = $request->user()->load('currentLevel');
        abort_unless($this->unlock->tenseTopicOpen($user, $topic) || $user->isStaff(), 403, __('Tense ini belum terbuka.'));
        $topic->load('exam', 'requiredLevel');

        return view('learner.tense', [
            'topic' => $topic,
            'row' => $this->progress->find($user, $topic),
        ]);
    }
}
