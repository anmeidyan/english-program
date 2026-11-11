<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AuditLogResource extends Resource
{
    use AuthorizesByPermission;

    protected static string $permission = 'users.manage';

    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Audit log';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Oleh'),
                Tables\Columns\TextColumn::make('action')->badge()->searchable(),
                Tables\Columns\TextColumn::make('subject_type'),
                Tables\Columns\TextColumn::make('subject_id'),
                Tables\Columns\TextColumn::make('meta')->formatStateUsing(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE))->limit(80)->wrap(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('action')->options(fn () => AuditLog::query()->distinct()->pluck('action', 'action')->all())]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
