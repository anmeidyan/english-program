<x-filament-panels::page>
    <form wire:submit="import" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">Import</x-filament::button>
    </form>

    @if ($report = $this->getReport())
        <x-filament::section wire:poll.3s>
            <x-slot name="heading">Laporan import: {{ $report['status'] ?? '' }}</x-slot>
            @if (! empty($report['stats']))
                <p class="text-sm">{{ collect($report['stats'])->map(fn ($v, $k) => "$k: $v")->implode(' · ') }}</p>
            @endif
            @if (! empty($report['errors']))
                <ul class="mt-3 text-sm text-danger-600 list-disc pl-5">
                    @foreach (array_slice($report['errors'], 0, 200) as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
