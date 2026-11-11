<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\SubmissionStatus;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\SubmissionResource\Pages;
use App\Models\Submission;
use App\Services\SettingsService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Review queue for speaking & writing (PRD 7.1). SLA: 48 hours. */
class SubmissionResource extends Resource
{
    use AuthorizesByPermission;

    protected static string $permission = 'submissions.review';

    protected static ?string $model = Submission::class;

    protected static ?string $navigationIcon = 'heroicon-o-microphone';

    protected static ?string $navigationGroup = 'Penilaian';

    protected static ?string $navigationLabel = 'Antrean review';

    public static function getNavigationBadge(): ?string
    {
        $count = Submission::query()->where('status', '!=', SubmissionStatus::Reviewed->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        $sla = (int) app(SettingsService::class)->get('review.sla_hours', 48);

        return $table
            ->defaultSort('created_at')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Masuk')->since()->sortable()
                    ->color(fn (Submission $r) => $r->status !== SubmissionStatus::Reviewed && $r->created_at->diffInHours(now()) > $sla - 12 ? 'danger' : null),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('user.name')->label('Pelajar')->searchable(),
                Tables\Columns\TextColumn::make('attemptAnswer.attempt.exam.title')->label('Ujian')->limit(30),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (SubmissionStatus $state) => $state->value),
                Tables\Columns\TextColumn::make('reviewer.name')->label('Reviewer'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['pending_review' => 'Pending', 'ai_draft' => 'AI draft', 'reviewed' => 'Reviewed'])->default('pending_review'),
                Tables\Filters\SelectFilter::make('type')->options(['speaking' => 'Speaking', 'writing' => 'Writing']),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('Nilai')->icon('heroicon-o-pencil-square')]);
    }

    /** @return Builder<Submission> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'reviewer', 'attemptAnswer.attempt.exam', 'attemptAnswer.question']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubmissions::route('/'),
            'edit' => Pages\ReviewSubmission::route('/{record}/review'),
        ];
    }
}
