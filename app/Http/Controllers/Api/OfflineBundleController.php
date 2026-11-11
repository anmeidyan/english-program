<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Unit;
use App\Services\CurriculumService;
use App\Services\OfflineBundleService;
use App\Services\Progress\UnlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineBundleController extends Controller
{
    public function __construct(
        private readonly OfflineBundleService $bundles,
        private readonly CurriculumService $curriculum,
        private readonly UnlockService $unlock,
    ) {}

    public function lesson(Lesson $lesson): JsonResponse
    {
        return response()->json($this->bundles->bundle($lesson));
    }

    /** All lessons of a unit the learner can already open ("Unduh untuk offline" per unit). */
    public function unit(Request $request, Unit $unit): JsonResponse
    {
        $lessons = $this->curriculum->lessonsOfUnit($unit)
            ->filter(fn (Lesson $l) => $this->unlock->isLessonOpen($request->user(), $l))
            ->map(fn (Lesson $l) => $this->bundles->bundle(Lesson::query()->findOrFail($l->id)))
            ->values();

        return response()->json(['unit' => $unit->only(['id', 'title', 'slug']), 'lessons' => $lessons]);
    }
}
