<?php

declare(strict_types=1);

namespace App\Filament\Resources\LessonResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VocabulariesRelationManager extends RelationManager
{
    protected static string $relationship = 'vocabularies';

    protected static ?string $title = 'Kosakata (masuk flashcard)';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('word')
            ->columns([
                Tables\Columns\TextColumn::make('word'),
                Tables\Columns\TextColumn::make('meaning_id')->label('Arti'),
            ])
            ->headerActions([Tables\Actions\AttachAction::make()->preloadRecordSelect()])
            ->actions([Tables\Actions\DetachAction::make()]);
    }
}
