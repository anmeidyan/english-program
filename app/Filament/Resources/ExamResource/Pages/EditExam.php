<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function afterSave(): void
    {
        /** @var Exam $exam */
        $exam = $this->record;
        $exam->update(['question_count' => (int) $exam->rules()->sum('count')]);
    }
}
