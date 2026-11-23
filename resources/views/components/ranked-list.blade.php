@props([
    'items',
    'empty',
    'link' => null,
])

@if($items->isEmpty())
    <x-site-analytics::empty>{{ $empty }}</x-site-analytics::empty>
@else
    <ol {{ $attributes->merge(['class' => 'space-y-3']) }}>
        @foreach($items as $item)
            <li data-ranked-item="{{ $item['label'] }}" data-visitors="{{ $item['visitors'] }}" data-page-views="{{ $item['page_views'] }}">
                <div class="flex items-baseline justify-between gap-4 text-sm">
                    @if($link && ($href = $link($item['label'])))
                        <a href="{{ $href }}" target="_blank" rel="noopener" class="truncate text-zinc-700 hover:underline dark:text-zinc-300">{{ $item['label'] }}</a>
                    @else
                        <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $item['label'] }}</span>
                    @endif
                    <span class="whitespace-nowrap tabular-nums text-zinc-500 dark:text-zinc-400">{{ number_format($item['visitors']) }}</span>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-blue-600 dark:bg-blue-400" style="width: {{ $item['share'] }}%"></div>
                </div>
            </li>
        @endforeach
    </ol>
@endif
