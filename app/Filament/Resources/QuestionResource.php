<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/** Question bank (PRD 7.1). payload/answer_key follow docs/content-format.md. */
class QuestionResource extends Resource
{
    use AuthorizesByPermission;

    protected static string $permission = 'questions.manage';

    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Bank soal & ujian';

    protected static ?string $navigationLabel = 'Bank soal';

    public static function form(Form $form): Form
    {
        $json = fn (string $name, string $label, string $help) => Forms\Components\Textarea::make($name)
            ->label($label)->rows(8)->helperText($help)->extraInputAttributes(['class' => 'font-mono text-xs'])
            ->formatStateUsing(fn ($state) => $state === null ? '{}' : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->dehydrateStateUsing(fn (?string $state) => json_decode((string) $state, true) ?? [])
            ->rule('json');

        return $form->schema([
            Forms\Components\Section::make()->columns(4)->schema([
                Forms\Components\Select::make('type')->options(QuestionType::options())->required()->live(),
                Forms\Components\Select::make('skill')->options(Skill::options())->required(),
                Forms\Components\Select::make('level_id')->relationship('level', 'name'),
                Forms\Components\Select::make('difficulty')->options(['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'])->default(1)->required(),
                Forms\Components\Textarea::make('prompt')->required()->columnSpanFull(),
                Forms\Components\TextInput::make('points')->numeric()->minValue(1)->default(1),
                Forms\Components\Toggle::make('is_active')->default(true)->inline(false),
                Forms\Components\Select::make('tags')->relationship('tags', 'name')->multiple()->searchable()->preload()
                    ->createOptionForm([Forms\Components\TextInput::make('name')->required()])->columnSpan(2),
            ]),
            Forms\Components\Section::make('Isi soal & kunci (JSON)')->columns(2)->schema([
                $json('payload', 'Payload (dikirim ke pelajar)', 'mcq: {"options":[{"key":"a","text":"..."}]} · fill_blank: {"blanks":1} · word_order: {"tokens":[...]} · matching: {"left":[...],"right":[...]} · listening: {"tts_text":"..."} · reading: {"passage":"..."}'),
                $json('answer_key', 'Answer key (tidak pernah dikirim ke client)', 'mcq_single: {"correct":"a"} · mcq_multi: {"correct":["a","c"],"partial":true} · tf: {"correct":"not_given"} · fill_blank: {"blanks":[["are"]]} · word_order/transformation/error_correction: {"accepted":["..."]} · matching: {"pairs":{"l1":"r1"}} · dictation: {"text":"..."}'),
                Forms\Components\Textarea::make('explanation')->label('Pembahasan')->columnSpanFull(),
                Forms\Components\FileUpload::make('media_path')->label('Audio/gambar')
                    ->disk(config('englishpath.media.disk'))->directory('question-media')
                    ->acceptedFileTypes(['audio/mpeg', 'audio/ogg', 'image/webp', 'image/jpeg', 'image/png'])->maxSize(10240),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('skill'),
                Tables\Columns\TextColumn::make('level.code')->label('Level'),
                Tables\Columns\TextColumn::make('difficulty')->label('Kesulitan'),
                Tables\Columns\TextColumn::make('prompt')->limit(60)->searchable()->wrap(),
                Tables\Columns\TextColumn::make('tags.name')->badge()->limitList(3),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(QuestionType::options()),
                Tables\Filters\SelectFilter::make('skill')->options(Skill::options()),
                Tables\Filters\SelectFilter::make('level_id')->relationship('level', 'name')->label('Level'),
                Tables\Filters\SelectFilter::make('tags')->relationship('tags', 'name')->multiple()->searchable(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make(), Tables\Actions\RestoreAction::make()])
            ->bulkActions([
                Tables\Actions\BulkAction::make('deactivate')->label('Nonaktifkan')->action(fn ($records) => $records->each->update(['is_active' => false])),
            ]);
    }

    /** @return Builder<Question> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['level', 'tags'])->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
