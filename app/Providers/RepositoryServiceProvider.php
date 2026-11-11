<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\CurriculumRepositoryInterface;
use App\Repositories\Contracts\ProgressRepositoryInterface;
use App\Repositories\Eloquent\EloquentCurriculumRepository;
use App\Repositories\Eloquent\EloquentProgressRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        CurriculumRepositoryInterface::class => EloquentCurriculumRepository::class,
        ProgressRepositoryInterface::class => EloquentProgressRepository::class,
    ];
}
