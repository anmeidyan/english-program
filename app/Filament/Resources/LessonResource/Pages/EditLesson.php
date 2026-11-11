<?php

declare(strict_types=1);

namespace App\Filament\Resources\LessonResource\Pages;

use App\Filament\Resources\LessonResource;
use App\Models\Lesson;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLesson extends EditRecord
{
    protected static string $resource = LessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')->label('Preview sebagai pelajar')->icon('heroicon-o-eye')
                ->url(fn (Lesson $record) => route('lessons.show', ['lesson' => $record, 'preview' => 1]))->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
