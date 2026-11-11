<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\TenseTopicResource\Pages;
use App\Models\TenseTopic;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenseTopicResource extends Resource
{
    use AuthorizesByPermission;

    protected static string $permission = 'lessons.manage';

    protected static ?string $model = TenseTopic::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Kurikulum';

    protected static ?string $navigationLabel = 'Tenses Master';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
                Forms\Components\Select::make('required_level_id')->relationship('requiredLevel', 'name')->label('Terbuka mulai level'),
                Forms\Components\Select::make('time')->options(['present' => 'present', 'past' => 'past', 'future' => 'future', 'past future' => 'past future'])->required(),
                Forms\Components\Select::make('aspect')->options(['simple' => 'simple', 'continuous' => 'continuous', 'perfect' => 'perfect', 'perfect continuous' => 'perfect continuous'])->required(),
                Forms\Components\TextInput::make('sort_order')->numeric(),
                Forms\Components\TextInput::make('formula.positive')->label('Rumus (+)')->required(),
                Forms\Components\TextInput::make('formula.negative')->label('Rumus (−)')->required(),
                Forms\Components\TextInput::make('formula.question')->label('Rumus (?)')->required(),
                Forms\Components\TagsInput::make('content.function')->label('Fungsi')->columnSpanFull(),
                Forms\Components\TagsInput::make('content.signals')->label('Time signal')->columnSpanFull(),
                Forms\Components\Textarea::make('content.compare')->label('Perbandingan dengan tense mirip')->columnSpanFull(),
                Forms\Components\Toggle::make('is_published'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('time'),
                Tables\Columns\TextColumn::make('aspect'),
                Tables\Columns\TextColumn::make('requiredLevel.code')->label('Level'),
                Tables\Columns\IconColumn::make('is_published')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTenseTopics::route('/')];
    }
}
