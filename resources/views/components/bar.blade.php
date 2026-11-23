@props([
    'label',
    'value',
    'share',
    'href' => null,
])

<div {{ $attributes }}>
    <div class="sa-bar-head">
        @if($href)
            <a href="{{ $href }}" target="_blank" rel="noopener" class="sa-bar-label">{{ $label }}</a>
        @else
            <span class="sa-bar-label">{{ $label }}</span>
        @endif
        <span class="sa-bar-value">{{ $value }}</span>
    </div>
    <div class="sa-bar-track">
        <div class="sa-bar-fill" style="width: {{ max(0, min(100, (int) $share)) }}%"></div>
    </div>
</div>
