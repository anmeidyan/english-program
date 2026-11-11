<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lesson;
use App\Models\Level;
use App\Models\Unit;
use App\Repositories\Contracts\CurriculumRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Read-side navigation over the curriculum tree: Level → Module → Unit → Lesson.
 * The published, ordered structure is what drives the mastery gate.
 */
class CurriculumService
{
    public function __construct(private readonly CurriculumRepositoryInterface $repository) {}

    /** @return Collection<int, Level> */
    public function publishedLevels(): Collection
    {
        return $this->repository->publishedLevels();
    }

    public function tree(Level|int $level): ?Level
    {
        return $this->repository->levelTree($level instanceof Level ? $level->id : $level);
    }

    /** @return Collection<int, Unit> */
    public function unitsOf(Level|int $level): Collection
    {
        $tree = $this->tree($level);

        return $tree === null ? collect() : $tree->modules->flatMap(fn ($m) => $m->units)->values();
    }

    /** @return Collection<int, Lesson> */
    public function lessonsOfUnit(Unit $unit): Collection
    {
        $levelId = $this->levelIdOfUnit($unit);
        $match = $this->unitsOf($levelId)->firstWhere('id', $unit->id);

        return $match === null ? collect() : $match->lessons->values();
    }

    /** @return Collection<int, Lesson> */
    public function lessonsOfLevel(Level|int $level): Collection
    {
        return $this->unitsOf($level)->flatMap(fn (Unit $u) => $u->lessons)->values();
    }

    public function firstLesson(Unit $unit): ?Lesson
    {
        return $this->lessonsOfUnit($unit)->first();
    }

    public function firstUnit(Level $level): ?Unit
    {
        return $this->unitsOf($level)->first();
    }

    public function nextLesson(Lesson $lesson): ?Lesson
    {
        $lessons = $this->lessonsOfUnit($lesson->unit);
        $index = $lessons->search(fn (Lesson $l) => $l->id === $lesson->id);

        return $index === false ? null : $lessons->get($index + 1);
    }

    public function isLastLessonOfUnit(Lesson $lesson): bool
    {
        return $this->lessonsOfUnit($lesson->unit)->last()?->id === $lesson->id;
    }

    public function nextUnit(Unit $unit): ?Unit
    {
        $units = $this->unitsOf($this->levelIdOfUnit($unit));
        $index = $units->search(fn (Unit $u) => $u->id === $unit->id);

        return $index === false ? null : $units->get($index + 1);
    }

    public function nextLevel(Level $level): ?Level
    {
        return $this->publishedLevels()
            ->filter(fn (Level $l) => $l->sort_order > $level->sort_order)
            ->sortBy('sort_order')
            ->first();
    }

    public function firstPublishedLevel(): ?Level
    {
        return $this->publishedLevels()->sortBy('sort_order')->first();
    }

    public function levelOfLesson(Lesson $lesson): Level
    {
        $lesson->loadMissing('unit.module.level');

        return $lesson->unit->module->level;
    }

    public function levelOfUnit(Unit $unit): Level
    {
        $unit->loadMissing('module.level');

        return $unit->module->level;
    }

    public function levelIdOfUnit(Unit $unit): int
    {
        $unit->loadMissing('module');

        return $unit->module->level_id;
    }

    public function flush(): void
    {
        $this->repository->flush();
    }
}
