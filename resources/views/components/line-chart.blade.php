@props([
    'chart',
    'label',
])

<div {{ $attributes->merge(['class' => 'px-2 py-4 sm:px-4']) }}>
    <svg
        viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}"
        class="h-auto w-full overflow-visible text-blue-600 dark:text-blue-400"
        role="img"
        aria-label="{{ $label }}"
    >
        @foreach($chart['ticks'] as $tick)
            <line x1="{{ $chart['left'] }}" x2="{{ $chart['right'] }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" class="stroke-zinc-200 dark:stroke-zinc-800" stroke-width="1" @unless($loop->first) stroke-dasharray="3 3" @endunless />
            <text x="{{ $chart['left'] - 8 }}" y="{{ $tick['y'] }}" text-anchor="end" dominant-baseline="middle" class="fill-zinc-500 text-[11px] tabular-nums dark:fill-zinc-400">{{ number_format($tick['value']) }}</text>
        @endforeach

        @foreach($chart['x_labels'] as $xLabel)
            <text x="{{ $xLabel['x'] }}" y="{{ $chart['height'] - 6 }}" text-anchor="{{ $xLabel['anchor'] }}" class="fill-zinc-500 text-[11px] dark:fill-zinc-400">{{ $xLabel['label'] }}</text>
        @endforeach

        <path d="{{ $chart['area_path'] }}" fill="currentColor" fill-opacity="0.08" />
        <path d="{{ $chart['line_path'] }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />

        @php($hitWidth = count($chart['points']) > 1 ? ($chart['right'] - $chart['left']) / (count($chart['points']) - 1) : $chart['right'] - $chart['left'])
        @foreach($chart['points'] as $point)
            <g class="group" data-chart-point="{{ $point['date'] }}" data-chart-visitors="{{ $point['visitors'] }}">
                <rect x="{{ $point['x'] - $hitWidth / 2 }}" y="0" width="{{ $hitWidth }}" height="{{ $chart['bottom'] }}" fill="transparent" />
                <line x1="{{ $point['x'] }}" x2="{{ $point['x'] }}" y1="{{ $chart['top'] }}" y2="{{ $chart['bottom'] }}" class="stroke-zinc-400 opacity-0 group-hover:opacity-100 dark:stroke-zinc-500" stroke-width="1" pointer-events="none" />
                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="currentColor" class="stroke-white opacity-0 group-hover:opacity-100 dark:stroke-zinc-900" stroke-width="2" pointer-events="none" />
                <g class="opacity-0 group-hover:opacity-100" pointer-events="none">
                    <rect x="{{ $point['tooltip_x'] }}" y="{{ $point['tooltip_y'] }}" width="150" height="46" rx="6" class="fill-white stroke-zinc-200 dark:fill-zinc-800 dark:stroke-zinc-700" stroke-width="1" />
                    <text x="{{ $point['tooltip_x'] + 10 }}" y="{{ $point['tooltip_y'] + 18 }}" class="fill-zinc-500 text-[11px] dark:fill-zinc-400">{{ $point['label'] }}</text>
                    <text x="{{ $point['tooltip_x'] + 10 }}" y="{{ $point['tooltip_y'] + 36 }}" class="fill-zinc-950 text-[12px] font-semibold dark:fill-white">{{ number_format($point['visitors']) }} visitors · {{ number_format($point['page_views']) }} views</text>
                </g>
            </g>
        @endforeach
    </svg>
</div>
