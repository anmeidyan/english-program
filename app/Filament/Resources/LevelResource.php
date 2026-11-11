<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\LevelCode;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\LevelResource\Pages;
use App\Models\Level;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LevelResource extends Resource
{
    use AuthorizesByPermission;

    protected static string $permission = 'lessons.manage';

    protected static ?string $model = Level::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('code')
                ->options(collect(LevelCode::cases())->mapWithKeys(fn (LevelCode $c) => [$c->value => $c->label()]))
                ->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\TextInput::make('estimated_hours')->numeric()->minValue(0)->default(0),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_published')->label('Published'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('code')->formatStateUsing(fn (LevelCode $state) => $state->label())->badge(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('modules_count')->counts('modules')->label('Modul'),
                Tables\Columns\TextColumn::make('estimated_hours')->label('Jam')->suffix(' jam'),
                Tables\Columns\IconColumn::make('is_published')->boolean()->label('Published'),
            ])
            ->filters([Tables\Filters\TrashedFilter::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('publish')
                    ->label(fn (Level $record) => $record->is_published ? 'Unpublish' : 'Publish')
                    ->icon('heroicon-o-eye')
                    ->action(fn (Level $record) => $record->update(['is_published' => ! $record->is_published])),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ]);
    }

    /** @return Builder<Level> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageLevels::route('/')];
    }
}
