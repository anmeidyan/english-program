<?php

declare(strict_types=1);

namespace App\Filament\Resources\VocabularyResource\Pages;

use App\Filament\Resources\VocabularyResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageVocabularies extends ManageRecords
{
    protected static string $resource = VocabularyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
