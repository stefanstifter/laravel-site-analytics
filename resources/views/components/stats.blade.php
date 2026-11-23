@props([
    'count' => 4,
])

<div {{ $attributes->merge(['class' => 'sa-stats', 'style' => '--sa-stat-columns: '.max(1, (int) $count)]) }}>
    {{ $slot }}
</div>
