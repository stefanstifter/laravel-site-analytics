@props([
    'title',
    'period',
    'description' => null,
])

@php
    $navigation = collect(config('site-analytics.navigation', []))
        ->filter(fn (array $item): bool => Route::has($item['route']));
@endphp

<x-dynamic-component :component="config('site-analytics.layout')" :title="__('Analytics').' · '.$title">
    <div {{ $attributes->merge(['class' => 'mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8']) }} data-selected-period="{{ $period }}">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl" level="1">{{ __('Analytics') }}</flux:heading>

            <flux:button.group data-period-picker>
                @foreach(\Stifter\SiteAnalytics\Reports\AnalyticsPeriod::OPTIONS as $periodValue => $periodLabel)
                    <flux:button
                        :href="request()->fullUrlWithQuery(['period' => $periodValue])"
                        :variant="$period === $periodValue ? 'primary' : 'outline'"
                        size="sm"
                        wire:navigate
                        :aria-current="$period === $periodValue ? 'true' : null"
                    >
                        {{ $periodLabel }}
                    </flux:button>
                @endforeach
            </flux:button.group>
        </div>

        <flux:navbar class="-mb-px mt-4 border-b border-zinc-200 dark:border-zinc-700" data-analytics-tabs>
            @foreach($navigation as $item)
                <flux:navbar.item
                    :href="route($item['route'], ['period' => $period])"
                    :current="request()->routeIs($item['route'])"
                    wire:navigate
                >
                    {{ __($item['label']) }}
                </flux:navbar.item>
            @endforeach
        </flux:navbar>

        @if($description)
            <flux:text class="mt-4">{{ $description }}</flux:text>
        @endif

        <div class="mt-6 space-y-6">
            {{ $slot }}
        </div>
    </div>
</x-dynamic-component>
