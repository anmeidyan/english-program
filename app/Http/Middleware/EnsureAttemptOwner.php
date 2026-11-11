<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ExamAttempt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttemptOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $attempt = $request->route('attempt');
        abort_unless($attempt instanceof ExamAttempt && $attempt->user_id === $request->user()?->id, 403);

        return $next($request);
    }
}
