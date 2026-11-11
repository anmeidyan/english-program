<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\AuditLogger;
use App\Services\SettingsService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** PRD 7.1 Settings: default passing scores, cooldowns, review SLA, AI grader on/off. */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Sistem';

    protected static ?string $title = 'Pengaturan';

    protected static string $view = 'filament.pages.settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('settings.manage');
    }

    public function mount(SettingsService $settings): void
    {
        $all = $settings->all();
        $this->form->fill([
            'passing_lesson' => $all['passing_score.lesson_quiz'],
            'passing_unit' => $all['passing_score.unit_exam'],
            'passing_level' => $all['passing_score.level_exam'],
            'passing_tenses' => $all['passing_score.tenses_checkpoint'],
            'min_skill_unit' => $all['min_skill_score.unit_exam'],
            'min_skill_level' => $all['min_skill_score.level_exam'],
            'cooldown_unit' => array_map('strval', $all['cooldown.unit_exam']),
            'cooldown_level' => array_map('strval', $all['cooldown.level_exam']),
            'sla' => $all['review.sla_hours'],
            'ai' => (bool) $all['ai_grader.enabled'],
            'audio' => $all['audio.max_plays_default'],
        ]);
    }

    public function form(Form $form): Form
    {
        $pct = fn (string $name, string $label) => Forms\Components\TextInput::make($name)->label($label)->numeric()->minValue(0)->maxValue(100)->suffix('%')->required();

        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Passing score default')->columns(4)->schema([
                $pct('passing_lesson', 'Lesson Quiz'), $pct('passing_unit', 'Unit Exam'), $pct('passing_level', 'Level Exam'), $pct('passing_tenses', 'Tenses Checkpoint'),
                $pct('min_skill_unit', 'Min. skill (unit)'), $pct('min_skill_level', 'Min. skill (level)'),
            ]),
            Forms\Components\Section::make('Cooldown default (menit)')->columns(2)->schema([
                Forms\Components\TagsInput::make('cooldown_unit')->label('Unit Exam'),
                Forms\Components\TagsInput::make('cooldown_level')->label('Level Exam'),
            ]),
            Forms\Components\Section::make('Lainnya')->columns(3)->schema([
                Forms\Components\TextInput::make('sla')->label('SLA review (jam)')->numeric()->minValue(1)->required(),
                Forms\Components\TextInput::make('audio')->label('Batas putar audio default')->numeric()->minValue(0)->required(),
                Forms\Components\Toggle::make('ai')->label('AI grader aktif')->helperText('Butuh AI_GRADER_DRIVER & AI_GRADER_API_KEY di .env')->inline(false),
            ]),
        ]);
    }

    public function save(SettingsService $settings, AuditLogger $audit): void
    {
        $d = $this->form->getState();
        $map = [
            'passing_score.lesson_quiz' => (int) $d['passing_lesson'],
            'passing_score.unit_exam' => (int) $d['passing_unit'],
            'passing_score.level_exam' => (int) $d['passing_level'],
            'passing_score.tenses_checkpoint' => (int) $d['passing_tenses'],
            'min_skill_score.unit_exam' => (int) $d['min_skill_unit'],
            'min_skill_score.level_exam' => (int) $d['min_skill_level'],
            'cooldown.unit_exam' => array_map('intval', $d['cooldown_unit']),
            'cooldown.level_exam' => array_map('intval', $d['cooldown_level']),
            'review.sla_hours' => (int) $d['sla'],
            'audio.max_plays_default' => (int) $d['audio'],
            'ai_grader.enabled' => (bool) $d['ai'],
        ];
        foreach ($map as $key => $value) {
            $settings->set($key, $value);
        }
        $audit->log(auth()->user(), 'settings.updated', null, $map);
        Notification::make()->success()->title('Pengaturan tersimpan. Berlaku untuk ujian baru.')->send();
    }
}
