<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Lesson;
use App\Models\Level;
use Illuminate\Support\Collection;

interface CurriculumRepositoryInterface
{
    /** @return Collection<int, Level> published levels ordered by sort_order */
    public function publishedLevels(): Collection;

    /** @return Collection<int, Level> all levels ordered by sort_order */
    public function allLevels(): Collection;

    /** Level with published modules → units → lessons (ordered), cached under the "curriculum" tag. */
    public function levelTree(int $levelId): ?Level;

    public function findLessonBySlug(string $slug): ?Lesson;

    public function flush(): void;
}
