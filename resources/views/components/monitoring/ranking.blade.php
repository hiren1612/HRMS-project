@props([
    'title',
    'rows',
    'column',
])

@php
    $hm = function (int $seconds): string {
        if ($seconds <= 0) return '0m';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    };

    $peak = $rows->max($column) ?: 1;
@endphp

<div {{ $attributes->merge(['class' => 'bg-white shadow-sm sm:rounded-lg p-5']) }}>
    <h3 class="font-semibold text-gray-800 mb-4">{{ $title }}</h3>

    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500">No tracked time on this day.</p>
    @else
        <ul class="space-y-3">
            @foreach ($rows as $row)
                <li>
                    <div class="flex items-baseline justify-between gap-3 mb-1">
                        <a href="{{ route('monitoring.show', $row['employee']) }}"
                           class="text-sm text-gray-700 truncate hover:underline">
                            {{ $row['employee']?->name ?? 'Unknown employee' }}
                        </a>
                        <span class="text-sm font-medium text-gray-800 shrink-0">{{ $hm($row[$column]) }}</span>
                    </div>
                    {{-- Bar is relative to the top row, not to an eight-hour
                         day: the comparison that matters here is between
                         people on the same day. --}}
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $column === 'idle_seconds' ? 'bg-amber-400' : 'bg-indigo-500' }}"
                             style="width: {{ max(2, round($row[$column] / $peak * 100)) }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
