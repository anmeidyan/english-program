@props(['label' => '', 'position' => 'now'])
{{-- Tense timeline past–now–future (PRD 4.1) --}}
@php
    $marks = [
        'past' => [['x' => 90, 'kind' => 'dot']],
        'now' => [['x' => 200, 'kind' => 'wave']],
        'future' => [['x' => 310, 'kind' => 'dot']],
        'repeated' => [['x' => 80, 'kind' => 'dot'], ['x' => 140, 'kind' => 'dot'], ['x' => 200, 'kind' => 'dot'], ['x' => 260, 'kind' => 'dot'], ['x' => 320, 'kind' => 'dot']],
        'past_to_now' => [['x' => 90, 'to' => 200, 'kind' => 'bar']],
        'before_past' => [['x' => 60, 'kind' => 'dot'], ['x' => 130, 'kind' => 'ref']],
    ][$position] ?? [];
@endphp
<figure class="card p-4">
    <svg viewBox="0 0 400 90" class="w-full h-auto" role="img" aria-label="{{ __('Garis waktu') }}: {{ $label }}">
        <line x1="20" y1="50" x2="380" y2="50" stroke="currentColor" class="text-slate-300 dark:text-slate-600" stroke-width="2"/>
        <polygon points="380,44 392,50 380,56" class="fill-slate-300 dark:fill-slate-600"/>
        <line x1="200" y1="38" x2="200" y2="62" stroke="#ef4444" stroke-width="2"/>
        <text x="200" y="80" text-anchor="middle" class="fill-red-500 text-[11px]">NOW</text>
        <text x="40" y="80" class="fill-slate-400 text-[11px]">PAST</text>
        <text x="330" y="80" class="fill-slate-400 text-[11px]">FUTURE</text>
        @foreach ($marks as $m)
            @if ($m['kind'] === 'dot')
                <circle cx="{{ $m['x'] }}" cy="50" r="7" fill="#2f6fe4"/>
            @elseif ($m['kind'] === 'ref')
                <line x1="{{ $m['x'] }}" y1="40" x2="{{ $m['x'] }}" y2="60" stroke="#64748b" stroke-width="2" stroke-dasharray="3 2"/>
            @elseif ($m['kind'] === 'bar')
                <rect x="{{ $m['x'] }}" y="44" width="{{ $m['to'] - $m['x'] }}" height="12" rx="6" fill="#2f6fe4" opacity=".8"/>
            @else
                <path d="M170 50 q7.5 -12 15 0 t15 0 t15 0 t15 0" fill="none" stroke="#2f6fe4" stroke-width="3"/>
            @endif
        @endforeach
    </svg>
    <figcaption class="text-sm text-slate-600 dark:text-slate-400 text-center">{{ $label }}</figcaption>
</figure>
