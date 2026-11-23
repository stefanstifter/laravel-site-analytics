<?php

namespace Stifter\SiteAnalytics;

use AndreasElia\Analytics\Agent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Visitor\VisitorIdentifier;

/**
 * Records analytics events for real visitors, skipping crawlers and (by default) logged-in users.
 */
class EventRecorder
{
    public function __construct(private readonly VisitorIdentifier $visitorIdentifier) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(Request $request, string $name, ?Model $subject = null, array $properties = []): ?AnalyticsEvent
    {
        $agent = $this->agent($request);

        if (! $this->shouldTrack($request, $agent)) {
            return null;
        }

        $event = new AnalyticsEvent([
            'name' => $name,
            'visitor' => $this->visitorIdentifier->identify($request),
            'session' => $request->hasSession() ? app(config('analytics.session.provider'))->get($request) : null,
            'device' => $agent->deviceType(),
            'properties' => $properties === [] ? null : $properties,
        ]);

        $event->subject()->associate($subject);
        $event->save();

        $this->pruneExpiredEventsByLottery();

        return $event;
    }

    /**
     * Record a search with its filters and result count. Consecutive keystrokes of one visitor are merged into a single search.
     *
     * @param  string  $context  Which search UI this came from, e.g. "race-listing".
     * @param  array<string, string>  $filters  Active filters, keyed by filter name; empty values are dropped.
     */
    public function recordSearch(Request $request, string $context, string $term, array $filters, int $results): ?AnalyticsEvent
    {
        if (! $this->shouldTrack($request)) {
            return null;
        }

        $properties = [
            'context' => $context,
            'term' => trim($term),
            'filters' => collect($filters)->filter(fn (?string $value): bool => filled($value))->sortKeys()->all(),
            'results' => $results,
        ];

        $previous = $this->refinableSearch($request, $properties);

        if ($previous !== null) {
            $previous->properties = $properties;
            $previous->updated_at = CarbonImmutable::now();
            $previous->save();

            return $previous;
        }

        return $this->record($request, AnalyticsEvent::SEARCH, properties: $properties);
    }

    /**
     * Delete events older than their configured retention.
     */
    public function pruneExpiredEvents(): int
    {
        $deleted = 0;

        foreach (config('site-analytics.retention_days', []) as $name => $days) {
            $deleted += AnalyticsEvent::query()
                ->where('name', $name)
                ->where('created_at', '<', CarbonImmutable::now()->subDays((int) $days))
                ->delete();
        }

        return $deleted;
    }

    /**
     * The visitor's previous search, if this one only continues it: typed further, corrected with backspace, or with filters adjusted in quick succession. Clearing the term starts a new search.
     *
     * @param  array{context: string, term: string, filters: array<string, string>, results: int}  $properties
     */
    private function refinableSearch(Request $request, array $properties): ?AnalyticsEvent
    {
        $previous = AnalyticsEvent::query()
            ->where('name', AnalyticsEvent::SEARCH)
            ->where('visitor', $this->visitorIdentifier->identify($request))
            ->where('updated_at', '>=', CarbonImmutable::now()->subSeconds((int) config('site-analytics.search_refine_seconds', 10)))
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($previous === null || ($previous->properties['context'] ?? null) !== $properties['context']) {
            return null;
        }

        $previousTerm = Str::lower((string) ($previous->properties['term'] ?? ''));
        $term = Str::lower($properties['term']);

        if ($term === '' && $previousTerm !== '') {
            return null;
        }

        return str_starts_with($term, $previousTerm) || str_starts_with($previousTerm, $term) ? $previous : null;
    }

    private function pruneExpiredEventsByLottery(): void
    {
        [$hits, $outOf] = config('site-analytics.prune_lottery', [1, 100]);

        if (random_int(1, (int) $outOf) <= (int) $hits) {
            $this->pruneExpiredEvents();
        }
    }

    public function shouldTrack(Request $request, ?Agent $agent = null): bool
    {
        if (! config('analytics.enabled')) {
            return false;
        }

        if (config('site-analytics.ignore_authenticated') && $request->user() !== null) {
            return false;
        }

        return ! ($agent ?? $this->agent($request))->isRobot();
    }

    private function agent(Request $request): Agent
    {
        $agent = new Agent;
        $agent->setUserAgent($request->userAgent() ?? '');
        $agent->setHttpHeaders($request->headers);

        return $agent;
    }
}
