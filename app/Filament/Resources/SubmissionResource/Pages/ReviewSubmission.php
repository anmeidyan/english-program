<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubmissionResource\Pages;

use App\Filament\Resources\SubmissionResource;
use App\Models\Submission;
use App\Services\Review\ReviewService;
use App\Services\Review\Rubric;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

/** Rubric form (0–9 per criterion), feedback, inline comments; AI draft prefilled when present. */
class ReviewSubmission extends EditRecord
{
    protected static string $resource = SubmissionResource::class;

    protected static ?string $title = 'Nilai jawaban';

    public function form(Form $form): Form
    {
        /** @var Submission $submission */
        $submission = $this->record;
        $question = $submission->attemptAnswer->question;
        $criteria = Rubric::criteria($submission->type);

        $answerView = $submission->type === 'speaking'
            ? ($submission->file_path
                ? '<audio controls preload="none" class="w-full" src="'.e(URL::temporarySignedRoute('recordings.show', now()->addHour(), $submission)).'"></audio>'
                : '<em>Tidak ada rekaman.</em>')
              .'<p class="mt-2 text-sm"><strong>Transkrip (STT):</strong> '.e((string) $submission->transcript).'</p>'
            : '<div class="whitespace-pre-line text-sm">'.e((string) $submission->text).'</div><p class="text-xs mt-2">'.str_word_count((string) $submission->text).' kata</p>';

        $draft = $submission->ai_score
            ? '<p class="text-sm"><strong>Draf AI ('.e((string) ($submission->ai_score['model'] ?? 'AI')).'):</strong> '
              .e(collect($submission->ai_score['scores'] ?? [])->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ')).'<br>'.e((string) ($submission->ai_score['feedback'] ?? '')).'</p>'
            : '';

        return $form->schema([
            Forms\Components\Section::make('Tugas')->schema([
                Forms\Components\Placeholder::make('prompt')->label('Soal')->content($question->prompt),
                Forms\Components\Placeholder::make('target')->label('Teks target')->content($question->payload['target_text'] ?? '—')->visible($submission->type === 'speaking'),
                Forms\Components\Placeholder::make('answer')->label('Jawaban pelajar')->content(new HtmlString($answerView)),
                Forms\Components\Placeholder::make('ai')->label('')->content(new HtmlString($draft))->visible($draft !== ''),
            ]),
            Forms\Components\Section::make('Rubrik (0–9)')->columns(4)->schema(
                collect($criteria)->map(fn (string $label, string $key) => Forms\Components\TextInput::make("final_score.{$key}")
                    ->label($label)->numeric()->minValue(0)->maxValue(9)->required())->values()->all()
            ),
            Forms\Components\Textarea::make('feedback')->label('Feedback untuk pelajar')->rows(4),
            Forms\Components\Repeater::make('inline_feedback')->label('Komentar inline (kutip kalimat + komentar)')
                ->schema([
                    Forms\Components\TextInput::make('quote')->label('Kutipan')->required(),
                    Forms\Components\TextInput::make('comment')->label('Komentar')->required(),
                ])->columns(2)->defaultItems(0),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Submission $submission */
        $submission = $this->record;
        if (empty($data['final_score']) && ! empty($submission->ai_score['scores'])) {
            $data['final_score'] = $submission->ai_score['scores'];
            $data['feedback'] ??= $submission->ai_score['feedback'] ?? null;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Submission $record */
        return app(ReviewService::class)->grade(
            $record,
            auth()->user(),
            array_map('intval', $data['final_score'] ?? []),
            $data['feedback'] ?? null,
            array_values($data['inline_feedback'] ?? []),
        );
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->success()->title('Nilai tersimpan; hasil ujian diperbarui.');
    }

    protected function getRedirectUrl(): string
    {
        return SubmissionResource::getUrl('index');
    }
}
