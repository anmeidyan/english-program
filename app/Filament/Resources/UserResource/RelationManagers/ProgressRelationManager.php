<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\ProgressStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProgressRelationManager extends RelationManager
{
    protected static string $relationship = 'progress';

    protected static ?string $title = 'Progres';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('progressable_type')->label('Jenis'),
                Tables\Columns\TextColumn::make('progressable_id')->label('ID'),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (ProgressStatus $state) => $state->value),
                Tables\Columns\TextColumn::make('best_score'),
                Tables\Columns\TextColumn::make('attempts_count')->label('Percobaan'),
                Tables\Columns\TextColumn::make('cooldown_until')->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ]);
    }
}
