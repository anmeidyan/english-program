<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamResource\RelationManagers;

use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Models\ExamQuestionRule;
use App\Services\Exam\QuestionPicker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Pool rules: filter by tag + skill + type + difficulty, N questions each. Pool should be ≥ 3× count. */
class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    protected static ?string $title = 'Aturan pengambilan soal';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('skill')->options(Skill::options()),
            Forms\Components\Select::make('question_type')->options(QuestionType::options())->helperText('Kosong = semua tipe objektif'),
            Forms\Components\Select::make('tag_id')->relationship('tag', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('count')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('difficulty_min')->numeric()->minValue(1)->maxValue(5)->default(1),
            Forms\Components\TextInput::make('difficulty_max')->numeric()->minValue(1)->maxValue(5)->default(5),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('skill'),
                Tables\Columns\TextColumn::make('question_type'),
                Tables\Columns\TextColumn::make('tag.name'),
                Tables\Columns\TextColumn::make('difficulty_min')->label('Diff')->formatStateUsing(fn ($state, ExamQuestionRule $r) => "{$r->difficulty_min}–{$r->difficulty_max}"),
                Tables\Columns\TextColumn::make('count')->label('Jumlah'),
                Tables\Columns\TextColumn::make('pool')->label('Pool')->state(fn (ExamQuestionRule $r) => count(app(QuestionPicker::class)->candidates($r)))
                    ->color(fn ($state, ExamQuestionRule $r) => $state >= $r->count * 3 ? 'success' : 'warning'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->after(fn () => $this->syncCount())])
            ->actions([
                Tables\Actions\EditAction::make()->after(fn () => $this->syncCount()),
                Tables\Actions\DeleteAction::make()->after(fn () => $this->syncCount()),
            ]);
    }

    private function syncCount(): void
    {
        $exam = $this->getOwnerRecord();
        $exam->update(['question_count' => (int) $exam->rules()->sum('count')]);
    }
}
