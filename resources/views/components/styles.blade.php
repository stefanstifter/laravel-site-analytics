{{-- The report styles, inlined once per page. The layout includes them; use this only for package components outside it. --}}
@once
    <style data-site-analytics-styles>{!! \Stifter\SiteAnalytics\SiteAnalyticsServiceProvider::css() !!}</style>
@endonce
