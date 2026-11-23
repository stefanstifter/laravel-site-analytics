<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Stifter\SiteAnalytics\Models\Click;
use Stifter\SiteAnalytics\Reports\AnalyticsPeriod;
use Stifter\SiteAnalytics\Reports\DailyChart;

/**
 * Which tracked links and buttons visitors click: all clicks, one group (?group=…, empty for ungrouped) or one link (?link=…).
 */
final class ClicksController
{
    private const LINK_LIMIT = 25;

    private const LIST_LIMIT = 10;

    /**
     * @var array<string, string>
     */
    private const DEVICE_LABELS = [
        'desktop' => 'Desktop',
        'phone' => 'Phone',
        'tablet' => 'Tablet',
    ];

    public function __invoke(Request $request): View
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $grouped = $request->query->has('group');
        $group = $grouped ? ($this->stringQuery($request, 'group') ?: null) : null;
        $link = $this->stringQuery($request, 'link') ?: null;

        if (! Click::query()->exists()) {
            return view('site-analytics::clicks', ['period' => $period->days, 'hasClicks' => false]);
        }

        $clicks = fn (): Builder => Click::query()
            ->whereBetween('created_at', [$period->startDate, $period->endDate])
            ->when($grouped, fn (Builder $query) => $query->inGroup($group))
            ->when($link !== null, fn (Builder $query) => $query->forLink($link));

        $totals = $clicks()->toBase()->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT visitor) as visitors')->first();
        $pageVisitors = $this->pageVisitors($period, $clicks()->select('uri')->distinct()->toBase());
        $days = $this->days($period, $clicks());
        $linkDetails = $link === null ? null : $clicks()->toBase()
            ->selectRaw('MAX(label) as label, MAX(href) as href, MAX(target_host) as target_host')
            ->first();

        return view('site-analytics::clicks', [
            'period' => $period->days,
            'hasClicks' => true,
            'grouped' => $grouped,
            'group' => $group,
            'link' => $link,
            'linkDetails' => $linkDetails,
            'summary' => [
                'clicks' => (int) $totals->clicks,
                'visitors' => (int) $totals->visitors,
                'page_visitors' => $pageVisitors,
                'click_rate' => $this->rate((int) $totals->visitors, $pageVisitors),
            ],
            'days' => $days,
            'chart' => DailyChart::build($days, 'clicks', fn (array $day): string => __(':clicks clicks · :visitors visitors', [
                'clicks' => number_format($day['clicks']),
                'visitors' => number_format($day['visitors']),
            ])),
            'groups' => $grouped ? collect() : $this->groups($clicks()),
            'links' => $link === null ? $this->links($clicks(), withGroup: ! $grouped) : collect(),
            'pages' => $this->pages($period, $clicks()),
            'destinations' => $link === null ? $this->destinations($clicks()) : collect(),
            'devices' => $this->devices($clicks()),
            'linkLimit' => self::LINK_LIMIT,
        ]);
    }

    /**
     * Clicks per group, most clicked first.
     *
     * @param  Builder<Click>  $clicks
     * @return Collection<int, array{group: string|null, clicks: int, visitors: int, links: int, share: int}>
     */
    private function groups(Builder $clicks): Collection
    {
        $rows = $clicks->toBase()
            ->select('group_name')
            ->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT visitor) as visitors, COUNT(DISTINCT COALESCE(href, label)) as links')
            ->groupBy('group_name')
            ->orderByDesc('clicks')
            ->limit(self::LINK_LIMIT)
            ->get();

        return $this->withShare($rows->map(fn (object $row): array => [
            'group' => $row->group_name,
            'clicks' => (int) $row->clicks,
            'visitors' => (int) $row->visitors,
            'links' => (int) $row->links,
        ]));
    }

    /**
     * The most clicked links, told apart by destination (or label, for buttons) within their group.
     *
     * @param  Builder<Click>  $clicks
     * @return Collection<int, array{key: string, label: string, href: string|null, target_host: string|null, group: string|null, clicks: int, visitors: int, share: int}>
     */
    private function links(Builder $clicks, bool $withGroup): Collection
    {
        $rows = $clicks->toBase()
            ->selectRaw('COALESCE(href, label) as link_key, MAX(label) as label, MAX(href) as href, MAX(target_host) as target_host')
            ->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT visitor) as visitors')
            ->when($withGroup, fn ($query) => $query->addSelect('group_name')->groupBy('group_name'))
            ->groupByRaw('COALESCE(href, label)')
            ->orderByDesc('clicks')
            ->orderBy('link_key')
            ->limit(self::LINK_LIMIT)
            ->get();

        return $this->withShare($rows->map(fn (object $row): array => [
            'key' => (string) $row->link_key,
            'label' => (string) $row->label,
            'href' => $row->href,
            'target_host' => $row->target_host,
            'group' => $row->group_name ?? null,
            'clicks' => (int) $row->clicks,
            'visitors' => (int) $row->visitors,
        ]));
    }

    /**
     * The pages with the most clicks, with the share of each page's visitors who clicked.
     *
     * @param  Builder<Click>  $clicks
     * @return Collection<int, array{uri: string, clicks: int, visitors: int, page_visitors: int, click_rate: int|null}>
     */
    private function pages(AnalyticsPeriod $period, Builder $clicks): Collection
    {
        $rows = $clicks->toBase()
            ->select('uri')
            ->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('uri')
            ->orderByDesc('clicks')
            ->orderBy('uri')
            ->limit(self::LIST_LIMIT)
            ->get();

        $pageVisitors = $period->humanPageViews()
            ->toBase()
            ->select('uri')
            ->selectRaw('COUNT(DISTINCT '.AnalyticsPeriod::VISITOR_SQL.') as visitors')
            ->whereIn('uri', $rows->pluck('uri'))
            ->groupBy('uri')
            ->pluck('visitors', 'uri');

        return $rows->map(fn (object $row): array => [
            'uri' => (string) $row->uri,
            'clicks' => (int) $row->clicks,
            'visitors' => (int) $row->visitors,
            'page_visitors' => (int) ($pageVisitors[$row->uri] ?? 0),
            'click_rate' => $this->rate((int) $row->visitors, (int) ($pageVisitors[$row->uri] ?? 0)),
        ]);
    }

    /**
     * Clicks per other site.
     *
     * @param  Builder<Click>  $clicks
     * @return Collection<int, array{host: string, clicks: int, visitors: int, share: int}>
     */
    private function destinations(Builder $clicks): Collection
    {
        $rows = $clicks->toBase()
            ->select('target_host')
            ->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT visitor) as visitors')
            ->whereNotNull('target_host')
            ->groupBy('target_host')
            ->orderByDesc('clicks')
            ->orderBy('target_host')
            ->limit(self::LIST_LIMIT)
            ->get();

        return $this->withShare($rows->map(fn (object $row): array => [
            'host' => (string) $row->target_host,
            'clicks' => (int) $row->clicks,
            'visitors' => (int) $row->visitors,
        ]));
    }

    /**
     * Clicks per device type, with each type's share of all clicks.
     *
     * @param  Builder<Click>  $clicks
     * @return Collection<int, array{type: string, label: string, clicks: int, share: int}>
     */
    private function devices(Builder $clicks): Collection
    {
        $byType = $clicks->toBase()
            ->select('device')
            ->selectRaw('COUNT(*) as clicks')
            ->groupBy('device')
            ->get()
            ->groupBy(fn (object $row): string => array_key_exists((string) $row->device, self::DEVICE_LABELS) ? $row->device : 'other')
            ->map(fn (Collection $rows): int => (int) $rows->sum('clicks'));

        $total = max(1, $byType->sum());

        return $byType
            ->map(fn (int $clicks, string $type): array => [
                'type' => $type,
                'label' => __(self::DEVICE_LABELS[$type] ?? 'Other'),
                'clicks' => $clicks,
                'share' => (int) round($clicks / $total * 100),
            ])
            ->sortByDesc('clicks')
            ->values();
    }

    /**
     * Clicks and clicking visitors for every day of the period, oldest first.
     *
     * @param  Builder<Click>  $clicks
     * @return Collection<int, array{date: string, label: string, clicks: int, visitors: int}>
     */
    private function days(AnalyticsPeriod $period, Builder $clicks): Collection
    {
        $rows = $clicks->toBase()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as clicks, COUNT(DISTINCT visitor) as visitors')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('day');

        return collect(range(0, $period->days - 1))->map(function (int $offset) use ($period, $rows): array {
            $date = $period->startDate->addDays($offset);
            $row = $rows->get($date->toDateString());

            return [
                'date' => $date->toDateString(),
                'label' => $date->locale(app()->getLocale())->translatedFormat(__('M j, Y')),
                'clicks' => (int) ($row->clicks ?? 0),
                'visitors' => (int) ($row->visitors ?? 0),
            ];
        });
    }

    /**
     * Visitors in the period who viewed any of the given pages.
     */
    private function pageVisitors(AnalyticsPeriod $period, \Illuminate\Database\Query\Builder $uris): int
    {
        return (int) $period->humanPageViews()
            ->whereIn('uri', $uris)
            ->selectRaw('COUNT(DISTINCT '.AnalyticsPeriod::VISITOR_SQL.') as total')
            ->value('total');
    }

    /**
     * Clicking visitors as a percentage of page visitors, capped at 100 for pages whose views were not recorded.
     */
    private function rate(int $visitors, int $pageVisitors): ?int
    {
        return $pageVisitors > 0 ? min(100, (int) round($visitors / $pageVisitors * 100)) : null;
    }

    /**
     * Add each row's bar width relative to the most clicked row.
     *
     * @template T of array{clicks: int}
     *
     * @param  Collection<int, T>  $rows
     * @return Collection<int, T&array{share: int}>
     */
    private function withShare(Collection $rows): Collection
    {
        $top = max(1, (int) $rows->max('clicks'));

        return $rows->map(fn (array $row): array => [...$row, 'share' => (int) round($row['clicks'] / $top * 100)]);
    }

    private function stringQuery(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }
}
