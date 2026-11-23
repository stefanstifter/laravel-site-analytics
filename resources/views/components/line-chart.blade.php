@props([
    'chart',
    'label',
])

<div {{ $attributes->merge(['class' => 'sa-chart']) }}>
    <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" role="img" aria-label="{{ $label }}">
        @foreach($chart['ticks'] as $tick)
            <line x1="{{ $chart['left'] }}" x2="{{ $chart['right'] }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" class="sa-chart-grid" stroke-width="1" @unless($loop->first) stroke-dasharray="3 3" @endunless />
            <text x="{{ $chart['left'] - 8 }}" y="{{ $tick['y'] }}" text-anchor="end" dominant-baseline="middle" class="sa-chart-label">{{ number_format($tick['value']) }}</text>
        @endforeach

        @foreach($chart['x_labels'] as $xLabel)
            <text x="{{ $xLabel['x'] }}" y="{{ $chart['height'] - 6 }}" text-anchor="{{ $xLabel['anchor'] }}" class="sa-chart-label">{{ $xLabel['label'] }}</text>
        @endforeach

        <path d="{{ $chart['area_path'] }}" fill="currentColor" fill-opacity="0.08" />
        <path d="{{ $chart['line_path'] }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />

        @php($hitWidth = count($chart['points']) > 1 ? ($chart['right'] - $chart['left']) / (count($chart['points']) - 1) : $chart['right'] - $chart['left'])
        @foreach($chart['points'] as $point)
            <g class="sa-chart-point" data-chart-point="{{ $point['date'] }}" data-chart-visitors="{{ $point['visitors'] }}">
                <rect x="{{ $point['x'] - $hitWidth / 2 }}" y="0" width="{{ $hitWidth }}" height="{{ $chart['bottom'] }}" fill="transparent" />
                <g class="sa-chart-hover" pointer-events="none">
                    <line x1="{{ $point['x'] }}" x2="{{ $point['x'] }}" y1="{{ $chart['top'] }}" y2="{{ $chart['bottom'] }}" class="sa-chart-cursor" stroke-width="1" />
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="currentColor" class="sa-chart-dot" stroke-width="2" />
                    <rect x="{{ $point['tooltip_x'] }}" y="{{ $point['tooltip_y'] }}" width="150" height="46" rx="6" class="sa-chart-tooltip-box" stroke-width="1" />
                    <text x="{{ $point['tooltip_x'] + 10 }}" y="{{ $point['tooltip_y'] + 18 }}" class="sa-chart-label">{{ $point['label'] }}</text>
                    <text x="{{ $point['tooltip_x'] + 10 }}" y="{{ $point['tooltip_y'] + 36 }}" class="sa-chart-tooltip-value">{{ __(':visitors visitors · :views views', ['visitors' => number_format($point['visitors']), 'views' => number_format($point['page_views'])]) }}</text>
                </g>
            </g>
        @endforeach
    </svg>
</div>
