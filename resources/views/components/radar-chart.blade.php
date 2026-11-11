@props(['scores' => []])
{{-- 6-axis skill radar (PRD 6.1), pure SVG. Missing skills are drawn at 0 and labelled "—". --}}
@php
    $labels = ['grammar' => 'Grammar', 'vocabulary' => 'Vocab', 'reading' => 'Reading', 'listening' => 'Listening', 'speaking' => 'Speaking', 'writing' => 'Writing'];
    $cx = 150; $cy = 130; $r = 95; $n = count($labels);
    $pt = fn (int $i, float $v) => [round($cx + $r * $v * sin(2 * M_PI * $i / $n), 1), round($cy - $r * $v * cos(2 * M_PI * $i / $n), 1)];
    $keys = array_keys($labels);
    $poly = collect($keys)->map(fn ($k, $i) => implode(',', $pt($i, min(100, (float) ($scores[$k] ?? 0)) / 100)))->implode(' ');
    $hasData = collect($scores)->filter(fn ($v) => $v !== null)->isNotEmpty();
@endphp
<figure {{ $attributes }}>
    <svg viewBox="0 0 300 270" class="w-full max-w-xs mx-auto" role="img"
         aria-label="{{ __('Skor per skill') }}: {{ collect($keys)->map(fn ($k) => $labels[$k].' '.($scores[$k] ?? '—'))->implode(', ') }}">
        @foreach ([0.25, 0.5, 0.75, 1] as $ring)
            <polygon points="{{ collect($keys)->map(fn ($k, $i) => implode(',', $pt($i, $ring)))->implode(' ') }}" fill="none" class="stroke-slate-200 dark:stroke-slate-700" stroke-width="1"/>
        @endforeach
        @foreach ($keys as $i => $k)
            @php [$x, $y] = $pt($i, 1); @endphp
            <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $x }}" y2="{{ $y }}" class="stroke-slate-200 dark:stroke-slate-700"/>
            @php [$lx, $ly] = $pt($i, 1.22); @endphp
            <text x="{{ $lx }}" y="{{ $ly }}" text-anchor="middle" dominant-baseline="middle" class="fill-slate-500 text-[11px]">{{ $labels[$k] }}</text>
            <text x="{{ $lx }}" y="{{ $ly + 12 }}" text-anchor="middle" class="fill-slate-900 dark:fill-white text-[10px] font-semibold">{{ isset($scores[$k]) && $scores[$k] !== null ? round($scores[$k]) : '—' }}</text>
        @endforeach
        @if ($hasData)
            <polygon points="{{ $poly }}" fill="#2f6fe4" fill-opacity=".25" stroke="#2f6fe4" stroke-width="2"/>
        @endif
    </svg>
    @unless ($hasData)
        <figcaption class="text-center text-xs text-slate-500">{{ __('Grafik muncul setelah ujian pertamamu.') }}</figcaption>
    @endunless
</figure>
