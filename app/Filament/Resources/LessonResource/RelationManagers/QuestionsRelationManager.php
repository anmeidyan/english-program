<?php

declare(strict_types=1);

namespace App\Filament\Resources\LessonResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Soal latihan';

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->recordTitleAttribute('prompt')
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('skill'),
                Tables\Columns\TextColumn::make('prompt')->limit(60)->wrap(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()->preloadRecordSelect()
                    ->recordSelectSearchColumns(['prompt']),
            ])
            ->actions([Tables\Actions\DetachAction::make()]);
    }
}
