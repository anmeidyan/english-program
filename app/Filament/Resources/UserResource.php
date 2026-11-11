<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Level;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Progress\ProgressResetService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use AuthorizesByPermission;

    protected static string $permission = 'users.manage';

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Pengguna';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\Select::make('roles')->relationship('roles', 'name')->multiple()->preload()
                ->options(collect(RoleName::cases())->mapWithKeys(fn ($r) => [$r->value => $r->value])),
            Forms\Components\Select::make('current_level_id')->relationship('currentLevel', 'name'),
            Forms\Components\TextInput::make('xp_total')->numeric()->disabled(),
            Forms\Components\TextInput::make('streak_current')->numeric()->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->badge(),
                Tables\Columns\TextColumn::make('currentLevel.code')->label('Level'),
                Tables\Columns\TextColumn::make('xp_total')->label('XP')->sortable(),
                Tables\Columns\TextColumn::make('streak_current')->label('Streak'),
                Tables\Columns\TextColumn::make('last_active_date')->date()->label('Aktif'),
            ])
            ->filters([Tables\Filters\SelectFilter::make('roles')->relationship('roles', 'name')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resetProgress')->label('Reset progres')->icon('heroicon-o-arrow-path')->color('danger')
                    ->visible(fn () => auth()->user()?->can('progress.reset'))
                    ->form([
                        Forms\Components\Select::make('level_id')->label('Level')->options(Level::query()->orderBy('sort_order')->pluck('name', 'id'))->required(),
                        Forms\Components\Textarea::make('reason')->label('Alasan (masuk audit log)')->required()->minLength(5),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data) {
                        $rows = app(ProgressResetService::class)->resetLevel(auth()->user(), $record, Level::findOrFail($data['level_id']), $data['reason']);
                        Notification::make()->success()->title("Progres direset ({$rows} baris).")->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ProgressRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function auditRoleChange(User $user, array $before): void
    {
        $after = $user->roles()->pluck('name')->sort()->values()->all();
        if ($before !== $after) {
            app(AuditLogger::class)->log(auth()->user(), 'user.roles_changed', $user, ['from' => $before, 'to' => $after]);
        }
    }
}
