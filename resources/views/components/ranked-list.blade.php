@props([
    'items',
    'empty',
    'link' => null,
])

@if($items->isEmpty())
    <x-site-analytics::empty>{{ $empty }}</x-site-analytics::empty>
@else
    <ol {{ $attributes->merge(['class' => 'sa-list']) }}>
        @foreach($items as $item)
            <li data-ranked-item="{{ $item['key'] ?? $item['label'] }}" data-visitors="{{ $item['visitors'] }}" data-page-views="{{ $item['page_views'] }}">
                <x-site-analytics::bar
                    :label="$item['label']"
                    :value="number_format($item['visitors'])"
                    :share="$item['share']"
                    :href="$link ? $link($item['label']) : null"
                />
            </li>
        @endforeach
    </ol>
@endif
