<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jobs\ImportContent as ImportContentJob;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** PRD 7.2: import curriculum (JSON) or questions (CSV) on the queue with a per-row error report. */
class ImportContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Kurikulum';

    protected static ?string $title = 'Import konten';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.import-content';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?string $reportKey = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('lessons.manage');
    }

    public function mount(): void
    {
        $this->form->fill(['kind' => 'curriculum_json', 'publish' => false]);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Radio::make('kind')->label('Jenis')->options([
                'curriculum_json' => 'Kurikulum (JSON, lihat docs/content-format.md)',
                'questions_csv' => 'Bank soal (CSV)',
            ])->required(),
            Forms\Components\FileUpload::make('file')->label('File')->disk('local')->directory('imports')
                ->acceptedFileTypes(['application/json', 'text/csv', 'text/plain'])->maxSize(10240)->required(),
            Forms\Components\Toggle::make('publish')->label('Langsung publish'),
        ]);
    }

    public function import(): void
    {
        $d = $this->form->getState();
        $this->reportKey = 'import-report:'.Str::uuid();
        Cache::put($this->reportKey, ['status' => 'queued'], now()->addDay());
        ImportContentJob::dispatch($d['kind'], $d['file'], (bool) $d['publish'], $this->reportKey, auth()->id());
        Notification::make()->success()->title('Import masuk antrean.')->send();
    }

    /** @return array<string, mixed>|null */
    public function getReport(): ?array
    {
        return $this->reportKey ? Cache::get($this->reportKey) : null;
    }
}
