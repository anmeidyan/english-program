<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\UserFlashcard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FlashcardPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $userId = $request->user()->id;

        return view('learner.flashcards', [
            'total' => UserFlashcard::query()->where('user_id', $userId)->count(),
            'learned' => UserFlashcard::query()->where('user_id', $userId)->where('repetitions', '>', 0)->count(),
        ]);
    }
}
