<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Lesson;
use App\Models\Level;
use App\Repositories\Contracts\CurriculumRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EloquentCurriculumRepository implements CurriculumRepositoryInterface
{
    private const TAG = 'curriculum';

    private const TTL = 3600;

    /** @var array<int, Level|null> */
    private array $memo = [];

    public function publishedLevels(): Collection
    {
        return Level::query()->published()->orderBy('sort_order')->get();
    }

    public function allLevels(): Collection
    {
        return Level::query()->orderBy('sort_order')->get();
    }

    public function levelTree(int $levelId): ?Level
    {
        if (array_key_exists($levelId, $this->memo)) {
            return $this->memo[$levelId];
        }

        return $this->memo[$levelId] = Cache::tags([self::TAG])->remember(
            "level-tree:{$levelId}",
            self::TTL,
            fn () => Level::query()
                ->with([
                    'exam',
                    'modules' => fn ($q) => $q->published()->orderBy('sort_order'),
                    'modules.units' => fn ($q) => $q->published()->orderBy('sort_order'),
                    'modules.units.exam',
                    'modules.units.lessons' => fn ($q) => $q->published()->orderBy('sort_order'),
                    'modules.units.lessons.exam',
                ])
                ->find($levelId),
        );
    }

    public function findLessonBySlug(string $slug): ?Lesson
    {
        return Lesson::query()->with('unit.module.level')->where('slug', $slug)->first();
    }

    public function flush(): void
    {
        $this->memo = [];
        Cache::tags([self::TAG])->flush();
    }
}
