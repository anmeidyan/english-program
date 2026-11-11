<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    RateLimitServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
    VoltServiceProvider::class,
];
