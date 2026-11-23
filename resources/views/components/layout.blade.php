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
        ['label' => 'Clicks', 'route' => $routePrefix.'clicks'],
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

            @if(Route::has($routePrefix.'system'))
                <a
                    href="{{ route($routePrefix.'system') }}"
                    class="sa-tab sa-tab-end"
                    wire:navigate
                    data-tab="{{ $routePrefix }}system"
                    @if(request()->routeIs($routePrefix.'system')) aria-current="page" @endif
                >
                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M6.455 1.45A.5.5 0 0 1 6.952 1h2.096a.5.5 0 0 1 .497.45l.186 1.858a4.996 4.996 0 0 1 1.466.848l1.703-.769a.5.5 0 0 1 .639.206l1.047 1.814a.5.5 0 0 1-.14.656l-1.517 1.09a5.026 5.026 0 0 1 0 1.694l1.516 1.09a.5.5 0 0 1 .141.656l-1.047 1.814a.5.5 0 0 1-.639.206l-1.703-.768c-.433.36-.928.649-1.466.847l-.186 1.858a.5.5 0 0 1-.497.45H6.952a.5.5 0 0 1-.497-.45l-.186-1.858a4.993 4.993 0 0 1-1.466-.848l-1.703.769a.5.5 0 0 1-.639-.206l-1.047-1.814a.5.5 0 0 1 .14-.656l1.517-1.09a5.033 5.033 0 0 1 0-1.694l-1.516-1.09a.5.5 0 0 1-.141-.656L2.46 3.593a.5.5 0 0 1 .639-.206l1.703.769c.433-.36.928-.65 1.466-.848l.186-1.858Zm-.177 7.567-.022-.037a2 2 0 0 1 3.466-1.997l.022.037a2 2 0 0 1-3.466 1.997Z" clip-rule="evenodd" /></svg>
                    {{ __('System') }}
                </a>
            @endif
        </nav>

        @if($description)
            <p class="sa-description">{{ $description }}</p>
        @endif

        <div class="sa-content">
            {{ $slot }}
        </div>
    </div>
</x-dynamic-component>
