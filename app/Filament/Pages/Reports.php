<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\ReportService;
use Filament\Pages\Page;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Sistem';

    protected static ?string $title = 'Laporan';

    protected static string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('reports.view');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $reports = app(ReportService::class);

        return [
            'overview' => $reports->overview(),
            'funnel' => $reports->funnel(),
            'exams' => $reports->examStats(),
            'missed' => $reports->mostMissed(),
        ];
    }
}
