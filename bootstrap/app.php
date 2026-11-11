<?php

use App\Exceptions\ExamException;
use App\Http\Middleware\EnsureAttemptOwner;
use App\Http\Middleware\EnsureContentUnlocked;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class, SecurityHeaders::class]);
        $middleware->alias([
            'unlocked' => EnsureContentUnlocked::class,
            'owner' => EnsureAttemptOwner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ExamException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'reason' => $e->decision?->reason,
                    'until' => $e->decision?->until?->toIso8601String(),
                    'redirect' => $e->redirectUrl,
                ], $e->status);
            }
            if ($e->decision !== null) {
                return response()->view('errors.locked', ['decision' => $e->decision], $e->status);
            }

            return back()->with('error', $e->getMessage());
        });
    })->create();
