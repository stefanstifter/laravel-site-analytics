@props([
    'count' => 4,
])

<div {{ $attributes->class([
    'grid grid-cols-1 gap-4 sm:grid-cols-2',
    match ((int) $count) {
        1 => 'sm:grid-cols-1',
        3 => 'lg:grid-cols-3',
        2 => '',
        default => 'lg:grid-cols-4',
    },
]) }}>
    {{ $slot }}
</div>
