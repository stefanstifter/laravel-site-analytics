<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Reports;

use AndreasElia\Analytics\Models\PageView;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The selectable reporting window for the admin analytics pages, ending today.
 */
final class AnalyticsPeriod
{
    /**
     * @var array<int, string>
     */
    public const OPTIONS = [
        7 => 'Last 7 days',
        30 => 'Last 30 days',
        365 => 'Last 12 months',
    ];

    public const DEFAULT_DAYS = 30;

    /**
     * SQL expression identifying a visitor: the daily visitor hash, falling back to the session for page views recorded before visitor IDs existed.
     */
    public const VISITOR_SQL = 'COALESCE(visitor, session)';

    public function __construct(
        public readonly int $days,
        public readonly CarbonImmutable $startDate,
        public readonly CarbonImmutable $endDate,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $days = (int) $request->input('period', self::DEFAULT_DAYS);
        $days = array_key_exists($days, self::OPTIONS) ? $days : self::DEFAULT_DAYS;
        $today = CarbonImmutable::today();

        return new self($days, $today->subDays($days - 1)->startOfDay(), $today->endOfDay());
    }

    /**
     * Page views in the period, without crawler traffic recorded before robots were excluded at tracking time.
     *
     * @return Builder<PageView>
     */
    public function humanPageViews(): Builder
    {
        return PageView::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->where(fn (Builder $query) => $query->whereNull('device')->orWhere('device', '!=', 'robot'));
    }
}
