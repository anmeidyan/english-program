<x-filament-panels::page>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach (['active_7d' => 'Pelajar aktif (7 hari)', 'learners' => 'Total pelajar', 'attempts_today' => 'Ujian hari ini', 'pending_reviews' => 'Menunggu review'] as $k => $label)
            <x-filament::section><div class="text-2xl font-bold">{{ $overview[$k] }}</div><div class="text-sm text-gray-500">{{ $label }}</div></x-filament::section>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">Funnel per level</x-slot>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th>Level</th><th>Mencapai</th><th>Lulus</th></tr></thead>
            <tbody>@foreach ($funnel as $row)<tr class="border-t"><td class="py-1">{{ $row['level'] }}</td><td>{{ $row['reached'] }}</td><td>{{ $row['passed'] }}</td></tr>@endforeach</tbody>
        </table>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Rata-rata skor & tingkat gagal per ujian</x-slot>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th>Ujian</th><th>Jenis</th><th>Percobaan</th><th>Rata-rata</th><th>Gagal</th></tr></thead>
            <tbody>
                @forelse ($exams as $row)
                    <tr class="border-t"><td class="py-1">{{ $row->title }}</td><td>{{ $row->type }}</td><td>{{ $row->attempts }}</td><td>{{ $row->avg_score }}</td><td>{{ $row->fail_rate }}%</td></tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-gray-500">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Soal paling sering salah (indikasi soal buruk)</x-slot>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th>ID</th><th>Soal</th><th>Tipe</th><th>Dijawab</th><th>Salah</th></tr></thead>
            <tbody>
                @forelse ($missed as $row)
                    <tr class="border-t"><td class="py-1"><a class="text-primary-600 underline" href="{{ \App\Filament\Resources\QuestionResource::getUrl('edit', ['record' => $row->id]) }}">{{ $row->id }}</a></td><td>{{ \Illuminate\Support\Str::limit($row->prompt, 70) }}</td><td>{{ $row->type }}</td><td>{{ $row->answered }}</td><td>{{ $row->wrong_rate }}%</td></tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-gray-500">Belum ada data (minimal 3 jawaban per soal).</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
