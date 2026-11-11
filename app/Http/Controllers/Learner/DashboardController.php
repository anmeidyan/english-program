<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->onboarded_at === null && ! $user->isStaff()) {
            return redirect()->route('onboarding');
        }

        return view('learner.dashboard', $this->dashboard->summary($user) + ['user' => $user]);
    }
}
