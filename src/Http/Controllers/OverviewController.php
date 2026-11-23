<?php

namespace Stifter\SiteAnalytics\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Stifter\SiteAnalytics\GeoIp\Country;
use Stifter\SiteAnalytics\GeoIp\CountryDatabase;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\Reports\AnalyticsPeriod;

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
            'chart' => $this->chart($days),
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

    /**
     * Build the SVG geometry for the daily visitors line chart.
     *
     * @param  Collection<int, array{date: string, label: string, visitors: int, page_views: int}>  $days
     * @return array{width: int, height: int, top: int, bottom: int, left: int, right: int, line_path: string, area_path: string, ticks: array<int, array{value: int, y: float}>, points: array<int, array{x: float, y: float, date: string, label: string, visitors: int, page_views: int, tooltip_x: float, tooltip_y: float}>, x_labels: array<int, array{x: float, label: string, anchor: string}>}
     */
    private function chart(Collection $days): array
    {
        $width = 800;
        $height = 260;
        $left = 44;
        $right = $width - 12;
        $top = 16;
        $bottom = $height - 28;
        $tooltipWidth = 150;

        $maxValue = $this->niceCeiling((int) $days->max('visitors'));
        $count = $days->count();
        $step = $count > 1 ? ($right - $left) / ($count - 1) : 0;

        $points = $days->values()->map(function (array $day, int $index) use ($left, $bottom, $top, $step, $maxValue, $count, $right, $tooltipWidth): array {
            $x = $count > 1 ? $left + $index * $step : ($left + $right) / 2;
            $y = $bottom - ($day['visitors'] / $maxValue) * ($bottom - $top);

            return [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'date' => $day['date'],
                'label' => $day['label'],
                'visitors' => $day['visitors'],
                'page_views' => $day['page_views'],
                'tooltip_x' => round(min(max($x - $tooltipWidth / 2, $left), $right - $tooltipWidth), 2),
                'tooltip_y' => round(max($y - 58, 0), 2),
            ];
        });

        $linePath = $points
            ->map(fn (array $point, int $index): string => ($index === 0 ? 'M' : 'L').$point['x'].' '.$point['y'])
            ->implode(' ');
        $areaPath = $points->isEmpty()
            ? ''
            : $linePath.' L'.$points->last()['x'].' '.$bottom.' L'.$points->first()['x'].' '.$bottom.' Z';

        $ticks = collect(range(0, 4))->map(fn (int $index): array => [
            'value' => (int) ($maxValue / 4 * $index),
            'y' => round($bottom - ($bottom - $top) / 4 * $index, 2),
        ])->all();

        $labelIndexes = array_unique([0, intdiv($count - 1, 2), $count - 1]);
        $xLabels = collect($labelIndexes)->map(fn (int $index, int $position): array => [
            'x' => $points[$index]['x'],
            'label' => CarbonImmutable::parse($points[$index]['date'])->locale(app()->getLocale())->translatedFormat(__('M j')),
            'anchor' => match (true) {
                $index === 0 => 'start',
                $index === $count - 1 => 'end',
                default => 'middle',
            },
        ])->values()->all();

        return [
            'width' => $width,
            'height' => $height,
            'top' => $top,
            'bottom' => $bottom,
            'left' => $left,
            'right' => $right,
            'line_path' => $linePath,
            'area_path' => $areaPath,
            'ticks' => $ticks,
            'points' => $points->all(),
            'x_labels' => $xLabels,
        ];
    }

    /**
     * Round the axis maximum up to a readable value that splits into four integer gridline steps.
     */
    private function niceCeiling(int $value): int
    {
        if ($value <= 4) {
            return 4;
        }

        $rawStep = $value / 4;
        $magnitude = 10 ** (int) floor(log10($rawStep));

        foreach ([1, 1.25, 1.5, 2, 2.5, 3, 4, 5, 6, 7.5, 8, 10] as $multiplier) {
            $step = (int) ceil($multiplier * $magnitude);

            if ($step >= $rawStep) {
                return $step * 4;
            }
        }

        return (int) ceil($rawStep) * 4;
    }
}
