<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ExamType;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\ExamResource\Pages;
use App\Filament\Resources\ExamResource\RelationManagers;
use App\Models\Exam;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Exam settings + question pool rules (PRD 7.1). Every lesson/unit/level/tense gets one automatically. */
class ExamResource extends Resource
{
    use AuthorizesByPermission;

    protected static string $permission = 'exams.manage';

    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Bank soal & ujian';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('title')->required()->columnSpan(2),
                Forms\Components\Select::make('type')->options(ExamType::options())->disabled(),
                Forms\Components\TextInput::make('duration_minutes')->numeric()->minValue(1)->helperText('Kosongkan = tanpa batas'),
                Forms\Components\TextInput::make('passing_score')->numeric()->minValue(1)->maxValue(100)->suffix('%')->required(),
                Forms\Components\TextInput::make('min_skill_score')->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                Forms\Components\TagsInput::make('cooldown_rules')->label('Cooldown (menit, per kegagalan)')->placeholder('60')
                    ->helperText('Mis. 60, 360, 1440 — nilai terakhir berulang.')
                    ->dehydrateStateUsing(fn ($state) => array_values(array_map('intval', (array) $state))),
                Forms\Components\TextInput::make('max_audio_plays')->numeric()->minValue(0)->maxValue(10)->helperText('0 = tanpa batas'),
                Forms\Components\Toggle::make('is_published')->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->formatStateUsing(fn (ExamType $state) => $state->label())->badge(),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(45),
                Tables\Columns\TextColumn::make('question_count')->label('Soal'),
                Tables\Columns\TextColumn::make('passing_score')->suffix('%'),
                Tables\Columns\TextColumn::make('attempts_count')->counts('attempts')->label('Percobaan'),
                Tables\Columns\IconColumn::make('is_published')->boolean(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('type')->options(ExamType::options())])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\RulesRelationManager::class];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExams::route('/'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }
}
