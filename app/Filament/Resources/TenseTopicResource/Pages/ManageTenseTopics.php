<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenseTopicResource\Pages;

use App\Filament\Resources\TenseTopicResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTenseTopics extends ManageRecords
{
    protected static string $resource = TenseTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
