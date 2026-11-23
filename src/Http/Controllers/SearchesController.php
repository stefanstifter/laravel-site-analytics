<?php

namespace Stifter\SiteAnalytics\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Reports\AnalyticsPeriod;

class SearchesController
{
    private const TERM_LIMIT = 25;

    private const COMBINATION_LIMIT = 15;

    public function __invoke(Request $request): View
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $searches = fn (): Builder => AnalyticsEvent::query()
            ->where('name', AnalyticsEvent::SEARCH)
            ->whereBetween('created_at', [$period->startDate, $period->endDate]);

        $grammar = $searches()->getQuery()->getGrammar();
        $termSql = 'LOWER('.$grammar->wrap('properties->term').')';
        $withoutResultsSql = 'SUM(CASE WHEN '.$grammar->wrap('properties->results').' = 0 THEN 1 ELSE 0 END)';

        $totals = $searches()
            ->selectRaw('COUNT(*) as searches')
            ->selectRaw('COUNT(DISTINCT '.AnalyticsPeriod::VISITOR_SQL.') as searchers')
            ->selectRaw($withoutResultsSql.' as without_results')
            ->toBase()
            ->first();

        $terms = $searches()
            ->selectRaw($termSql.' as term')
            ->selectRaw('COUNT(*) as searches')
            ->selectRaw('COUNT(DISTINCT '.AnalyticsPeriod::VISITOR_SQL.') as searchers')
            ->selectRaw($withoutResultsSql.' as without_results')
            ->whereRaw($termSql." <> ''")
            ->groupByRaw($termSql)
            ->toBase()
            ->get();

        [$filterUsage, $combinations] = $this->filterStatistics($searches());

        return view('site-analytics::searches', [
            'period' => $period->days,
            'summary' => [
                'searches' => (int) $totals->searches,
                'searchers' => (int) $totals->searchers,
                'without_results' => (int) $totals->without_results,
                'without_results_share' => $totals->searches > 0 ? (int) round($totals->without_results / $totals->searches * 100) : null,
            ],
            'topTerms' => $terms->sortBy([['searches', 'desc'], ['term', 'asc']])->take(self::TERM_LIMIT)->values(),
            'termsWithoutResults' => $terms->where('without_results', '>', 0)->sortBy([['without_results', 'desc'], ['term', 'asc']])->take(self::TERM_LIMIT)->values(),
            'filterUsage' => $filterUsage,
            'combinations' => $combinations,
            'termLimit' => self::TERM_LIMIT,
        ]);
    }

    /**
     * Count how often each filter value and each combination of filters was used.
     *
     * @param  Builder<AnalyticsEvent>  $searches
     * @return array{0: Collection<string, array{label: string, total: int, values: Collection<int, array{label: string, searches: int, share: int}>}>, 1: Collection<int, array{label: string, searches: int}>}
     */
    private function filterStatistics(Builder $searches): array
    {
        $valueCounts = [];
        $combinationCounts = [];

        foreach ($searches->select(['id', 'properties'])->lazyById(1000) as $search) {
            $filters = $search->properties['filters'] ?? [];

            if ($filters === []) {
                continue;
            }

            foreach ($filters as $name => $value) {
                $valueCounts[$name][$value] = ($valueCounts[$name][$value] ?? 0) + 1;
            }

            $combination = collect($filters)
                ->map(fn (int|string $value, int|string $name): string => $this->label($name).': '.$this->label($value))
                ->implode(' · ');
            $combinationCounts[$combination] = ($combinationCounts[$combination] ?? 0) + 1;
        }

        $filterUsage = collect($valueCounts)
            ->map(function (array $values, int|string $name): array {
                $total = array_sum($values);

                return [
                    'label' => $this->label($name),
                    'total' => $total,
                    'values' => collect($values)
                        ->map(fn (int $count, int|string $value): array => [
                            'label' => $this->label($value),
                            'searches' => $count,
                            'share' => (int) round($count / $total * 100),
                        ])
                        ->sortByDesc('searches')
                        ->values(),
                ];
            })
            ->sortByDesc('total');

        $combinations = collect($combinationCounts)
            ->map(fn (int $count, int|string $label): array => ['label' => (string) $label, 'searches' => $count])
            ->sortBy([['searches', 'desc'], ['label', 'asc']])
            ->take(self::COMBINATION_LIMIT)
            ->values();

        return [$filterUsage, $combinations];
    }

    /**
     * Turn identifiers like "team_4" into "Team 4"; free text such as locations is shown as entered.
     */
    private function label(int|string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[a-z0-9_-]+$/', $value) === 1 ? Str::headline($value) : $value;
    }
}
