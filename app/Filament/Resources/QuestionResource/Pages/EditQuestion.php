<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use Filament\Resources\Pages\EditRecord;

class EditQuestion extends EditRecord
{
    protected static string $resource = QuestionResource::class;

    /** answer_key/explanation are $hidden on the model, so add them back for the admin form. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['answer_key'] = $this->record->getAttribute('answer_key');
        $data['explanation'] = $this->record->getAttribute('explanation');

        return $data;
    }
}
