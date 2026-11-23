@props([
    'title',
    'period' => null,
    'description' => null,
])

@php
    $routePrefix = config('site-analytics.routes.name');
    $navigation = collect([
        ['label' => 'Overview', 'route' => $routePrefix.'overview'],
        ['label' => 'Searches', 'route' => $routePrefix.'searches'],
        ...config('site-analytics.extra_navigation', []),
    ])->filter(fn (array $item): bool => Route::has($item['route']));
@endphp

<x-dynamic-component :component="config('site-analytics.layout') ?? 'site-analytics::page'" :title="__('Analytics').' · '.$title">
    <x-site-analytics::styles />

    <div {{ $attributes->merge(['class' => 'sa']) }} data-sa-dark-mode="{{ config('site-analytics.dark_mode', 'media') }}" @if($period) data-selected-period="{{ $period }}" @endif>
        <div class="sa-header">
            <h1 class="sa-title">{{ __('Analytics') }}</h1>

            @if($period)
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
            @endif
        </div>

        <nav class="sa-tabs" aria-label="{{ __('Reports') }}" data-analytics-tabs>
            @foreach($navigation as $item)
                <a
                    href="{{ route($item['route'], array_filter(['period' => $period])) }}"
                    class="sa-tab"
                    wire:navigate
                    data-tab="{{ $item['route'] }}"
                    @if(request()->routeIs($item['route'])) aria-current="page" @endif
                >{{ __($item['label']) }}</a>
            @endforeach
        </nav>

        @if($description)
            <p class="sa-description">{{ $description }}</p>
        @endif

        <div class="sa-content">
            {{ $slot }}
        </div>
    </div>
</x-dynamic-component>
