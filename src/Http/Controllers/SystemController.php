<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Stifter\SiteAnalytics\ClickRecorder;
use Stifter\SiteAnalytics\EventRecorder;
use Stifter\SiteAnalytics\GeoIp\Country;
use Stifter\SiteAnalytics\GeoIp\CountryDatabase;
use Stifter\SiteAnalytics\GeoIp\CountryDatabaseUpdateFailed;
use Stifter\SiteAnalytics\GeoIp\CountryDatabaseUpdater;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Models\Click;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\SiteAnalyticsServiceProvider;

/**
 * What the package records and how, the country database's state, and the admin actions behind them.
 */
final class SystemController
{
    private const COVERAGE_DAYS = 7;

    public function __construct(
        private readonly CountryDatabase $countries,
        private readonly CountryDatabaseUpdater $countryUpdater,
    ) {}

    public function show(Request $request): View
    {
        $countryStatus = $this->countryUpdater->status();

        return view('site-analytics::system', [
            'canManage' => Gate::allows(SiteAnalyticsServiceProvider::MANAGE_GATE),
            'tracking' => [
                'enabled' => (bool) config('site-analytics.enabled'),
                'group' => config('site-analytics.track_group') ?: null,
                'exclude' => config('site-analytics.exclude', []),
                'ignored_ips' => config('site-analytics.ignored_ips', []),
                'ignore_robots' => (bool) config('site-analytics.ignore_robots'),
                'ignore_authenticated' => (bool) config('site-analytics.ignore_authenticated'),
                'clicks' => (bool) config('site-analytics.clicks.enabled', true),
                'outbound_clicks' => (bool) config('site-analytics.clicks.outbound', true),
                'last_click' => Click::query()->latest('id')->value('created_at'),
                'last_page_view' => PageView::query()->latest('id')->value('created_at'),
            ],
            'geoip' => [
                'state' => $this->countries->state(),
                'built_at' => $this->countries->builtAt(),
                'size' => $this->countries->size(),
                'path' => $this->countries->path(),
                'auto_update' => (bool) config('site-analytics.geoip.auto_update'),
                'max_age_days' => (int) config('site-analytics.geoip.max_age_days', 35),
                'your_ip' => $request->ip(),
                'your_country' => ($code = $this->countries->country($request->ip())) ? Country::label($code) : null,
                'coverage' => $this->coverage(),
                ...$countryStatus,
            ],
            'data' => [
                'page_views' => PageView::query()->count(),
                'first_page_view' => PageView::query()->min('created_at'),
                'events' => AnalyticsEvent::query()->toBase()->selectRaw('name, COUNT(*) as total')->groupBy('name')->orderBy('name')->pluck('total', 'name'),
                'clicks' => Click::query()->count(),
                'click_retention_days' => config('site-analytics.clicks.retention_days'),
                'retention_days' => config('site-analytics.retention_days', []),
                'prune_lottery' => config('site-analytics.prune_lottery', [1, 100]),
            ],
        ]);
    }

    public function prune(EventRecorder $events, ClickRecorder $clicks): RedirectResponse
    {
        $deletedEvents = $events->pruneExpiredEvents();
        $deletedClicks = $clicks->pruneExpired();

        return $this->back('success', __('Deleted :events and :clicks.', [
            'events' => trans_choice(':count expired event|:count expired events', $deletedEvents, ['count' => $deletedEvents]),
            'clicks' => trans_choice(':count expired click|:count expired clicks', $deletedClicks, ['count' => $deletedClicks]),
        ]));
    }

    public function updateCountryDatabase(): RedirectResponse
    {
        try {
            $this->countryUpdater->update();
        } catch (CountryDatabaseUpdateFailed $exception) {
            return $this->back('error', __('The update failed: :error', ['error' => $exception->getMessage()]));
        }

        return $this->back('success', __('Installed the country database from :date.', [
            'date' => $this->countries->builtAt()?->locale(app()->getLocale())->translatedFormat(__('M j, Y')),
        ]));
    }

    /**
     * Share of the last days' page views with a known country, or null without page views.
     */
    private function coverage(): ?int
    {
        $row = PageView::query()
            ->where('created_at', '>=', CarbonImmutable::now()->subDays(self::COVERAGE_DAYS))
            ->selectRaw('COUNT(*) as total, COUNT(country_code) as located')
            ->toBase()
            ->first();

        return $row->total > 0 ? (int) floor($row->located / $row->total * 100) : null;
    }

    private function back(string $type, string $message): RedirectResponse
    {
        return redirect()
            ->route(config('site-analytics.routes.name').'system')
            ->with('site-analytics.flash', ['type' => $type, 'message' => $message]);
    }
}
