<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Facades;

use Illuminate\Support\Facades\Facade;
use Stifter\SiteAnalytics\EventRecorder;

/**
 * Record events for the current request.
 *
 * @method static \Stifter\SiteAnalytics\Models\AnalyticsEvent|null event(string $name, \Illuminate\Database\Eloquent\Model|null $subject = null, array<string, mixed> $properties = [])
 * @method static \Stifter\SiteAnalytics\Models\AnalyticsEvent|null search(string $context, string $term, array<string, string> $filters = [], int $results = 0)
 * @method static \Stifter\SiteAnalytics\Models\AnalyticsEvent|null record(\Illuminate\Http\Request $request, string $name, \Illuminate\Database\Eloquent\Model|null $subject = null, array<string, mixed> $properties = [])
 * @method static \Stifter\SiteAnalytics\Models\AnalyticsEvent|null recordSearch(\Illuminate\Http\Request $request, string $context, string $term, array<string, string> $filters, int $results)
 * @method static int pruneExpiredEvents()
 *
 * @see EventRecorder
 */
final class SiteAnalytics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EventRecorder::class;
    }
}
