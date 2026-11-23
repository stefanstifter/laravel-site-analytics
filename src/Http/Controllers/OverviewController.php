<?php

namespace Stifter\SiteAnalytics\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Stifter\SiteAnalytics\GeoIp\Country;
use Stifter\SiteAnalytics\GeoIp\CountryDatabase;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Models\Click;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\Reports\AnalyticsPeriod;
use Stifter\SiteAnalytics\Reports\DailyChart;

class OverviewController
{
    /**
     * @var array<string, string>
     */
    private const DEVICE_LABELS = [
        'desktop' => 'Desktop',
        'phone' => 'Phone',
        'tablet' => 'Tablet',
    ];

    private const LIST_LIMIT = 10;

    public function __construct(private readonly CountryDatabase $countryDatabase) {}

    public function __invoke(Request $request): View
    {
        $period = AnalyticsPeriod::fromRequest($request);

        $dailyRows = $period->humanPageViews()
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(DISTINCT '.AnalyticsPeriod::VISITOR_SQL.') as visitors')
            ->selectRaw('COUNT(*) as page_views')
            ->selectRaw("COUNT(DISTINCT CASE WHEN device IN ('phone', 'tablet') THEN ".AnalyticsPeriod::VISITOR_SQL.' END) as mobile_visitors')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $days = $this->days($period, $dailyRows);
        $totalPageViews = $days->sum('page_views');
        $peakDay = $days->sortByDesc('visitors')->first();
        $devices = $this->devices($period);

        return view('site-analytics::overview', [
            'period' => $period->days,
            'days' => $days,
            'chart' => DailyChart::build($days, 'visitors', fn (array $day): string => __(':visitors visitors · :views views', [
                'visitors' => number_format($day['visitors']),
                'views' => number_format($day['page_views']),
            ])),
            'devices' => $devices,
            'pages' => $this->ranked($period->humanPageViews()->select('uri as label')->groupBy('uri')),
            'referrers' => $this->ranked($period->humanPageViews()->select('source as label')->whereNotNull('source')->where('source', '!=', '')->groupBy('source')),
            'campaigns' => $this->campaigns($period),
            'countries' => $this->ranked($period->humanPageViews()->select('country_code as label')->groupBy('country_code'))
                ->map(fn (array $row): array => [...$row, 'key' => $row['label'] ?: 'unknown', 'label' => Country::label($row['label'] ?: null)]),
            'countryDatabase' => $this->countryDatabase->state(),
            'summary' => [
                'total_visitors' => $days->sum('visitors'),
                'average_daily_visitors' => round($days->avg('visitors') ?? 0, 1),
                'peak_visitors' => $peakDay['visitors'] ?? 0,
                'peak_day' => $peakDay['label'] ?? null,
                'page_views' => $totalPageViews,
                ...$this->eventTotals($period),
                ...$this->clickTotal($period),
            ],
        ]);
    }

    /**
     * The most visited entries of a grouped page view query, with each bar's share relative to the top entry.
     *
     * @param  Builder<PageView>  $query  Grouped query selecting a "label" column.
     * @return Collection<int, array{label: string, visitors: int, page_views: int, share: int}>
     */
    private function ranked($query): Collection
    {
        $rows = $query
            ->selectRaw('COUNT(DISTINCT '.AnalyticsPeriod::VISITOR_SQL.') as visitors')
            ->selectRaw('COUNT(*) as page_views')
            ->orderByDesc('visitors')
            ->orderByDesc('page_views')
            ->limit(self::LIST_LIMIT)
            ->toBase()
            ->get();

        $topVisitors = max(1, (int) $rows->max('visitors'));

        return $rows->map(fn (object $row): array => [
            'label' => (string) $row->label,
            'visitors' => (int) $row->visitors,
            'page_views' => (int) $row->page_views,
            'share' => (int) round($row->visitors / $topVisitors * 100),
        ]);
    }

    /**
     * Visitors per UTM campaign, labelled "source / medium · campaign".
     *
     * @return Collection<int, array{label: string, visitors: int, page_views: int, share: int}>
     */
    private function campaigns(AnalyticsPeriod $period): Collection
    {
        $rows = $period->humanPageViews()
            ->select(['utm_source', 'utm_medium', 'utm_campaign'])
            ->selectRaw('COUNT(DISTINCT '.AnalyticsPeriod::VISITOR_SQL.') as visitors')
            ->selectRaw('COUNT(*) as page_views')
            ->where(fn ($query) => $query->whereNotNull('utm_source')->orWhereNotNull('utm_medium')->orWhereNotNull('utm_campaign'))
            ->groupBy(['utm_source', 'utm_medium', 'utm_campaign'])
            ->orderByDesc('visitors')
            ->limit(self::LIST_LIMIT)
            ->toBase()
            ->get();

        $topVisitors = max(1, (int) $rows->max('visitors'));

        return $rows->map(fn (object $row): array => [
            'label' => collect([
                collect([$row->utm_source, $row->utm_medium])->filter()->implode(' / '),
                $row->utm_campaign,
            ])->filter()->implode(' · '),
            'visitors' => (int) $row->visitors,
            'page_views' => (int) $row->page_views,
            'share' => (int) round($row->visitors / $topVisitors * 100),
        ]);
    }

    /**
     * Website clicks (unique visitors) and searches in the period, only for event types the app has ever recorded.
     *
     * @return array{website_clicks?: int, searches?: int}
     */
    private function eventTotals(AnalyticsPeriod $period): array
    {
        $recordedNames = AnalyticsEvent::query()
            ->whereIn('name', [AnalyticsEvent::OUTBOUND_CLICK, AnalyticsEvent::SEARCH])
            ->distinct()
            ->pluck('name');

        $events = fn (string $name) => AnalyticsEvent::query()
            ->where('name', $name)
            ->whereBetween('created_at', [$period->startDate, $period->endDate]);

        return array_filter([
            'website_clicks' => $recordedNames->contains(AnalyticsEvent::OUTBOUND_CLICK)
                ? (int) $events(AnalyticsEvent::OUTBOUND_CLICK)->selectRaw('COUNT(DISTINCT '.AnalyticsPeriod::VISITOR_SQL.') as total')->value('total')
                : null,
            'searches' => $recordedNames->contains(AnalyticsEvent::SEARCH)
                ? $events(AnalyticsEvent::SEARCH)->count()
                : null,
        ], fn (?int $total): bool => $total !== null);
    }

    /**
     * Clicks on tracked links in the period, once the app has recorded any.
     *
     * @return array{clicks?: int}
     */
    private function clickTotal(AnalyticsPeriod $period): array
    {
        if (! config('site-analytics.clicks.enabled', true) || ! Click::query()->exists()) {
            return [];
        }

        return ['clicks' => Click::query()->whereBetween('created_at', [$period->startDate, $period->endDate])->count()];
    }

    /**
     * Visitors per device type in the period, largest first.
     *
     * @return Collection<int, array{type: string, label: string, visitors: int, share: int}>
     */
    private function devices(AnalyticsPeriod $period): Collection
    {
        $visitorsByType = $period->humanPageViews()
            ->selectRaw('device')
            ->selectRaw('COUNT(DISTINCT '.AnalyticsPeriod::VISITOR_SQL.') as visitors')
            ->groupBy('device')
            ->get()
            ->groupBy(fn (PageView $row): string => array_key_exists((string) $row->device, self::DEVICE_LABELS) ? $row->device : 'other')
            ->map(fn (Collection $rows): int => (int) $rows->sum('visitors'));

        $totalVisitors = $visitorsByType->sum();

        return $visitorsByType
            ->map(fn (int $visitors, string $type): array => [
                'type' => $type,
                'label' => __(self::DEVICE_LABELS[$type] ?? 'Other'),
                'visitors' => $visitors,
                'share' => $totalVisitors > 0 ? (int) round($visitors / $totalVisitors * 100) : 0,
            ])
            ->sortByDesc('visitors')
            ->values();
    }

    /**
     * @param  Collection<string, PageView>  $dailyRows
     * @return Collection<int, array{date: string, label: string, visitors: int, page_views: int, mobile_share: ?int}>
     */
    private function days(AnalyticsPeriod $period, Collection $dailyRows): Collection
    {
        return collect(range(0, $period->days - 1))->map(function (int $offset) use ($period, $dailyRows): array {
            $date = $period->startDate->addDays($offset);
            $dateKey = $date->toDateString();
            $row = $dailyRows->get($dateKey);
            $visitors = (int) ($row?->visitors ?? 0);

            return [
                'date' => $dateKey,
                'label' => $date->locale(app()->getLocale())->translatedFormat(__('M j, Y')),
                'visitors' => $visitors,
                'page_views' => (int) ($row?->page_views ?? 0),
                'mobile_share' => $visitors > 0 ? (int) round((int) $row->mobile_visitors / $visitors * 100) : null,
            ];
        });
    }
}
