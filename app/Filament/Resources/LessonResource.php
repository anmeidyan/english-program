<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\LessonType;
use App\Enums\Skill;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\LessonResource\Pages;
use App\Filament\Resources\LessonResource\RelationManagers;
use App\Models\Lesson;
use Filament\Forms;
use Filament\Forms\Components\Builder as BlockBuilder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class LessonResource extends Resource
{
    use AuthorizesByPermission;

    protected static string $permission = 'lessons.manage';

    protected static ?string $model = Lesson::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Lesson')->columns(3)->schema([
                Forms\Components\Select::make('unit_id')->relationship('unit', 'title')->searchable()->preload()->required(),
                Forms\Components\TextInput::make('title')->required()->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                Forms\Components\TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
                Forms\Components\Select::make('type')->options(LessonType::options())->required(),
                Forms\Components\Select::make('skill')->options(Skill::options())->required(),
                Forms\Components\TextInput::make('estimated_minutes')->numeric()->minValue(5)->maxValue(60)->default(15),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                Forms\Components\Toggle::make('is_published'),
            ]),
            Forms\Components\Section::make('Lesson builder')
                ->description('Blok konten berurutan; disimpan sebagai JSON.')
                ->schema([self::contentBuilder()]),
        ])->columns(1);
    }

    public static function contentBuilder(): BlockBuilder
    {
        return BlockBuilder::make('content')
            ->label('Blok konten')
            ->collapsible()
            ->reorderableWithButtons()
            ->blocks([
                Block::make('heading')->icon('heroicon-o-hashtag')->schema([
                    Forms\Components\TextInput::make('text')->required(),
                ]),
                Block::make('rich_text')->icon('heroicon-o-document-text')->schema([
                    Forms\Components\RichEditor::make('html')->label('Teks')->required(),
                ]),
                Block::make('tense_formula')->icon('heroicon-o-variable')->columns(3)->schema([
                    Forms\Components\TextInput::make('positive')->label('(+)')->required(),
                    Forms\Components\TextInput::make('negative')->label('(−)')->required(),
                    Forms\Components\TextInput::make('question')->label('(?)')->required(),
                    Forms\Components\Textarea::make('note')->columnSpanFull(),
                ]),
                Block::make('example_list')->icon('heroicon-o-list-bullet')->schema([
                    Forms\Components\TextInput::make('title'),
                    Forms\Components\Repeater::make('items')->columns(2)->schema([
                        Forms\Components\TextInput::make('en')->label('English')->required(),
                        Forms\Components\TextInput::make('id')->label('Indonesia'),
                    ]),
                ]),
                Block::make('table')->icon('heroicon-o-table-cells')->schema([
                    Forms\Components\TextInput::make('caption'),
                    Forms\Components\TagsInput::make('headers')->required(),
                    Forms\Components\Repeater::make('rows')->simple(Forms\Components\TagsInput::make('cells'))
                        ->helperText('Satu baris = daftar sel, urut sesuai header.'),
                ]),
                Block::make('reading_passage')->icon('heroicon-o-newspaper')->schema([
                    Forms\Components\TextInput::make('title'),
                    Forms\Components\Textarea::make('text')->rows(8)->required(),
                ]),
                Block::make('audio')->icon('heroicon-o-speaker-wave')->schema([
                    Forms\Components\TextInput::make('caption'),
                    Forms\Components\FileUpload::make('url')->label('File audio (mp3/ogg, maks 10 MB)')
                        ->disk(config('englishpath.media.disk'))->directory('lesson-audio')
                        ->acceptedFileTypes(['audio/mpeg', 'audio/ogg'])->maxSize(10240),
                    Forms\Components\Textarea::make('tts_text')->label('Teks TTS (dipakai bila tidak ada file)'),
                    Forms\Components\Select::make('accent')->options(['en-US' => 'US', 'en-GB' => 'UK', 'en-AU' => 'AU'])->default('en-US'),
                    Forms\Components\Textarea::make('transcript'),
                ]),
                Block::make('image')->icon('heroicon-o-photo')->schema([
                    Forms\Components\FileUpload::make('url')->image()->disk(config('englishpath.media.disk'))->directory('lesson-images')->maxSize(2048),
                    Forms\Components\TextInput::make('alt')->required(),
                ]),
                Block::make('video_embed')->icon('heroicon-o-film')->schema([
                    Forms\Components\TextInput::make('url')->url()->required()->helperText('URL embed YouTube/Vimeo'),
                ]),
                Block::make('tip')->icon('heroicon-o-light-bulb')->schema([
                    Forms\Components\Textarea::make('text')->required(),
                ]),
                Block::make('common_mistake')->icon('heroicon-o-exclamation-triangle')->columns(2)->schema([
                    Forms\Components\TextInput::make('wrong')->required(),
                    Forms\Components\TextInput::make('right')->required(),
                    Forms\Components\Textarea::make('note')->columnSpanFull(),
                ]),
                Block::make('timeline')->icon('heroicon-o-arrows-right-left')->schema([
                    Forms\Components\TextInput::make('label')->required(),
                    Forms\Components\Select::make('position')->options(['past' => 'Past', 'now' => 'Now', 'future' => 'Future', 'repeated' => 'Repeated', 'past_to_now' => 'Past → now', 'before_past' => 'Before a past point'])->required(),
                    Forms\Components\TagsInput::make('points'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('unit.title')->label('Unit')->sortable()->limit(25),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('questions_count')->counts('questions')->label('Latihan'),
                Tables\Columns\IconColumn::make('is_published')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit_id')->relationship('unit', 'title')->label('Unit'),
                Tables\Filters\SelectFilter::make('type')->options(LessonType::options()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')->label('Preview')->icon('heroicon-o-eye')
                    ->url(fn (Lesson $record) => route('lessons.show', ['lesson' => $record, 'preview' => 1]))->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\QuestionsRelationManager::class, RelationManagers\VocabulariesRelationManager::class];
    }

    /** @return Builder<Lesson> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('unit')->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLessons::route('/'),
            'create' => Pages\CreateLesson::route('/create'),
            'edit' => Pages\EditLesson::route('/{record}/edit'),
        ];
    }
}
