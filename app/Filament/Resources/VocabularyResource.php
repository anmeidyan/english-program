<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\VocabularyResource\Pages;
use App\Models\Vocabulary;
use App\Services\MediaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class VocabularyResource extends Resource
{
    use AuthorizesByPermission;

    protected static string $permission = 'lessons.manage';

    protected static ?string $model = Vocabulary::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = 'Kurikulum';

    protected static ?string $navigationLabel = 'Kosakata';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        $disk = config('englishpath.media.disk');

        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('word')->required(),
                Forms\Components\TextInput::make('part_of_speech'),
                Forms\Components\Select::make('level_id')->relationship('level', 'name'),
                Forms\Components\TextInput::make('ipa_us')->label('IPA (US)'),
                Forms\Components\TextInput::make('ipa_uk')->label('IPA (UK)'),
                Forms\Components\TextInput::make('meaning_id')->label('Arti (Indonesia)')->required(),
                Forms\Components\Textarea::make('definition_en')->label('Definisi (English)')->columnSpan(2),
                Forms\Components\Textarea::make('example')->label('Contoh kalimat'),
                Forms\Components\FileUpload::make('audio_us')->label('Audio US')->disk($disk)->directory('vocab-audio')->acceptedFileTypes(['audio/mpeg', 'audio/ogg'])->maxSize(10240),
                Forms\Components\FileUpload::make('audio_uk')->label('Audio UK')->disk($disk)->directory('vocab-audio')->acceptedFileTypes(['audio/mpeg', 'audio/ogg'])->maxSize(10240),
                Forms\Components\FileUpload::make('image_path')->label('Gambar (auto → webp)')->image()->disk($disk)->directory('vocab-images')->maxSize(2048)
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => app(MediaService::class)->storeImageAsWebp($file, 'vocab-images')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('word')
            ->columns([
                Tables\Columns\TextColumn::make('word')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('part_of_speech'),
                Tables\Columns\TextColumn::make('ipa_us'),
                Tables\Columns\TextColumn::make('meaning_id')->label('Arti')->searchable(),
                Tables\Columns\TextColumn::make('level.code')->label('Level'),
            ])
            ->filters([Tables\Filters\SelectFilter::make('level_id')->relationship('level', 'name')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVocabularies::route('/')];
    }
}
