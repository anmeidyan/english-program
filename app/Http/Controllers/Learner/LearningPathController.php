<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearningPathController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): RedirectResponse
    {
        $level = $request->user()->currentLevel ?? Level::query()->published()->orderBy('sort_order')->firstOrFail();

        return redirect()->route('learn.level', $level);
    }

    public function show(Request $request, Level $level): View
    {
        abort_unless($level->is_published || $request->user()->isStaff(), 404);

        return view('learner.path', $this->dashboard->path($request->user(), $level) + [
            'levels' => Level::query()->published()->orderBy('sort_order')->get(),
        ]);
    }
}
