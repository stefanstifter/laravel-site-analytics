@props([
    'label',
    'value',
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'sa-stat']) }}>
    <div class="sa-stat-label">{{ $label }}</div>
    <div class="sa-stat-value">{{ $value }}</div>
    @if($hint)
        <div class="sa-stat-hint">{{ $hint }}</div>
    @endif
</div>
