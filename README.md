# Site Analytics for Laravel

Minimal, privacy-friendly site analytics for Stifter Software Laravel apps.

- Page views with hashed session IDs (never raw) and a cookie-free daily visitor ID
- Crawlers and, by default, logged-in users are not tracked
- Generic events (`analytics_events`) with an optional subject model and JSON properties
- Search tracking that merges a visitor's keystrokes into one search
- Admin report pages (Overview, Searches) built with Flux, extendable with your own tabs

## Requirements

- PHP 8.2+
- Laravel 12
- `livewire/flux` ^2.1

## Installation

Add the GitHub repository to the app's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/stefanstifter/laravel-site-analytics" }
]
```

```bash
composer require stifter/site-analytics:^0.2
php artisan migrate
```

The service provider is auto-discovered. Publish the config to customise it:

```bash
php artisan vendor:publish --tag=site-analytics-config
```

If the app uses Tailwind, add the package views as a source so their classes are compiled:

```css
@source '../../vendor/stifter/site-analytics/resources/views';
```

## Recording page views

Add the `site-analytics` middleware to the routes you want to track:

```php
Route::middleware('site-analytics')->group(function () {
    Route::view('/', 'welcome')->name('home');
    Route::get('about', AboutController::class)->name('about');
});
```

Use it inside the `web` group so page views get a session; without one, `session` stays empty. It records a page view after the response for `GET` requests, and skips the request when:

- tracking is disabled (`enabled`)
- the IP address is in `ignored_ips` or the path matches `exclude`
- the visitor is a bot (`ignore_robots`) or logged in (`ignore_authenticated`)

Each page view stores the path (`uri`), the referring host (`source`, `null` for referrers from the app's own domain with or without `www.`), `device` (`desktop`, `phone`, `tablet` or `robot`), `browser` (`Chrome`, `Safari`, `Firefox`, `Edge` or `Other`), `host`, the `utm_*` query parameters, a SHA-256 hash of the session ID (`session`) and a daily `visitor` ID.

## Configuration

See `config/site-analytics.php`:

- `enabled` – record page views and events at all (env `ANALYTICS_ENABLED`, default `true`)
- `exclude` – paths that are not recorded, in `$request->is()` syntax, e.g. `['dashboard', 'dashboard/*']`
- `ignored_ips` – IP addresses that are not recorded
- `ignore_robots` – skip crawlers and other bots (default `true`)
- `ignore_authenticated` – skip page views and events of logged-in users (default `true`)
- `routes` – prefix, route name prefix and middleware of the report pages (default `dashboard/analytics`, `admin.analytics.`)
- `layout` – Blade layout component the report pages render in (default `layouts.app`)
- `navigation` – tabs shown on every report page
- `search_refine_seconds` – window in which refined searches update the previous one
- `retention_days` – per event name, how long events are kept
- `prune_lottery` – chance that recording an event prunes expired ones (no scheduler needed)

## Adding a report tab

Register a route for your report page, then add it to `navigation` in the published config. Tabs whose route does not exist are skipped.

```php
// routes/web.php
Route::middleware(['auth', 'verified'])
    ->get('dashboard/analytics/races', RaceAnalyticsController::class)
    ->name('admin.analytics.races');
```

```php
// config/site-analytics.php
'navigation' => [
    ['label' => 'Overview', 'route' => 'admin.analytics.overview'],
    ['label' => 'Races', 'route' => 'admin.analytics.races'],
    ['label' => 'Searches', 'route' => 'admin.analytics.searches'],
],
```

Use the package's Blade components so the page matches the built-in ones:

```blade
<x-site-analytics::layout :title="__('Races')" :period="$period">
    <x-site-analytics::stats>
        <x-site-analytics::stat :label="__('Views')" :value="$views" />
    </x-site-analytics::stats>

    <x-site-analytics::panel :title="__('Top Races')">
        ...
    </x-site-analytics::panel>
</x-site-analytics::layout>
```

`Stifter\SiteAnalytics\Reports\AnalyticsPeriod::fromRequest($request)->humanPageViews()` gives the page views of the selected period without bots, and `AnalyticsPeriod::VISITOR_SQL` counts visitors the same way the built-in reports do.

## Recording events

```php
use Stifter\SiteAnalytics\EventRecorder;

app(EventRecorder::class)->record($request, 'outbound_click', $model, ['url' => $url]);

app(EventRecorder::class)->recordSearch($request, 'listing', $term, $filters, $resultCount);
```

Events follow `enabled` and `ignore_authenticated`, and are never recorded for bots.

## Upgrading from 0.1

0.2 no longer depends on `andreaselia/analytics`. Existing `page_views` data and migrations stay as they are; `php artisan migrate` runs nothing new on an install that is already on 0.1.

1. Update the package. This also removes `andreaselia/analytics`, `jenssegers/agent`, `mobiledetect/mobiledetectlib` and `doctrine/dbal`, unless the app requires them itself:

   ```bash
   composer require stifter/site-analytics:^0.2
   ```

2. Move the settings from `config/analytics.php` into `config/site-analytics.php`, then delete `config/analytics.php`:

   | `config/analytics.php` | `config/site-analytics.php` |
   | --- | --- |
   | `enabled` | `enabled` (same env `ANALYTICS_ENABLED`) |
   | `exclude` | `exclude` – list the admin paths here instead of `/analytics*` |
   | `ignoredIPs` | `ignored_ips` |
   | `ignoreRobots` | `ignore_robots` (now defaults to `true`) |
   | `session.provider` | removed – sessions are always hashed |
   | `prefix`, `domain`, `middleware`, `mask`, `ignoreMethods`, `ignoredColumns` | removed |

3. In `routes/web.php`, change `middleware('analytics')` to `middleware('site-analytics')`. The `analytics` alias still works in 0.2 but will be removed.

4. Replace `AndreasElia\Analytics\Models\PageView` with `Stifter\SiteAnalytics\Models\PageView` in app code, tests and migrations that import it (in ultracyclingraces: `RaceAnalyticsController`, several tests and the `remove_internal_referrers_from_page_views` migration). The new model has a factory: `PageView::factory()`.

5. Update tests that use `RequestSessionProvider`, `HashedSessionProvider`, `analytics.session.provider`, `configureTrackingDefaults()` or the `/analytics` redirect. The redirect is gone; the reports live under `routes.prefix`.

Other changes in recorded data:

- `country` is no longer filled. It held the browser language, not a country. The column stays.
- `source` is now the referrer's lowercased host. Hosts containing digits or ports are stored in full (previously `t3n.de` became `t`).
- Android tablets that `mobiledetect` did not know by model are now counted as `tablet` instead of `phone`. The mobile share (phone + tablet) is unchanged.
- `EventRecorder::shouldTrack()` takes a `Stifter\SiteAnalytics\Detection\UserAgent` instead of andreaselia's `Agent`.

## Development

```bash
composer test
composer lint
```

## License

MIT
