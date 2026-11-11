<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Exam;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Progress\AccessDecision;
use App\Services\Progress\UnlockService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side mastery gate on every lesson/exam request (PRD 8.3, 13.1).
 * Locked → 403 (JSON for the API, a page with a link to the right item for the web).
 * Staff may preview lessons with ?preview=1.
 */
class EnsureContentUnlocked
{
    public function __construct(private readonly UnlockService $unlock) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();
        $lesson = $request->route('lesson');
        $exam = $request->route('exam');

        $this->unlock->ensureInitialized($user);

        if ($lesson instanceof Lesson && $user->isStaff() && $request->boolean('preview')) {
            return $next($request);
        }

        $decision = match (true) {
            $lesson instanceof Lesson => $this->unlock->lessonDecision($user, $lesson),
            // the intro page also explains cooldown/remedial/pending states, so only "locked" blocks it
            $exam instanceof Exam => $this->examAccess($user, $exam, $request),
            default => AccessDecision::allow(),
        };

        if ($decision->allowed) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $decision->message,
                'reason' => $decision->reason,
                'redirect' => $decision->redirectUrl,
                'until' => $decision->until?->toIso8601String(),
            ], 403);
        }

        return response()->view('errors.locked', ['decision' => $decision], 403);
    }

    private function examAccess(User $user, Exam $exam, Request $request): AccessDecision
    {
        $decision = $this->unlock->examDecision($user, $exam);
        if ($decision->allowed || $request->expectsJson()) {
            return $decision;
        }

        return in_array($decision->reason, [AccessDecision::LOCKED, AccessDecision::NOT_READY, AccessDecision::UNPUBLISHED], true)
            ? $decision
            : AccessDecision::allow();
    }
}
