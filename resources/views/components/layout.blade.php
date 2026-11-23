@props([
    'title',
    'period',
    'description' => null,
])

@php
    $navigation = collect(config('site-analytics.navigation', []))
        ->filter(fn (array $item): bool => Route::has($item['route']));
@endphp

<x-dynamic-component :component="config('site-analytics.layout') ?? 'site-analytics::page'" :title="__('Analytics').' · '.$title">
    <x-site-analytics::styles />

    <div {{ $attributes->merge(['class' => 'sa']) }} data-sa-dark-mode="{{ config('site-analytics.dark_mode', 'media') }}" data-selected-period="{{ $period }}">
        <div class="sa-header">
            <h1 class="sa-title">{{ __('Analytics') }}</h1>

            <nav class="sa-periods" aria-label="{{ __('Period') }}" data-period-picker>
                @foreach(\Stifter\SiteAnalytics\Reports\AnalyticsPeriod::options() as $periodValue => $periodLabel)
                    <a
                        href="{{ request()->fullUrlWithQuery(['period' => $periodValue]) }}"
                        class="sa-period"
                        wire:navigate
                        @if($period === $periodValue) aria-current="true" @endif
                    >{{ $periodLabel }}</a>
                @endforeach
            </nav>
        </div>

        @if($navigation->count() > 1)
            <nav class="sa-tabs" aria-label="{{ __('Reports') }}" data-analytics-tabs>
                @foreach($navigation as $item)
                    <a
                        href="{{ route($item['route'], ['period' => $period]) }}"
                        class="sa-tab"
                        wire:navigate
                        @if(request()->routeIs($item['route'])) aria-current="page" @endif
                    >{{ __($item['label']) }}</a>
                @endforeach
            </nav>
        @endif

        @if($description)
            <p class="sa-description">{{ $description }}</p>
        @endif

        <div class="sa-content">
            {{ $slot }}
        </div>
    </div>
</x-dynamic-component>
