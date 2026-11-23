# Site Analytics for Laravel

Privacy-friendly page view and event tracking for Laravel, with report pages built in Flux. No cookies, no raw session IDs, no 40 MB JavaScript tracker. Just a middleware and a couple of tables.

- Page views with hashed sessions and a cookie-free daily visitor ID
- Bots and logged-in users are ignored by default (you know you visit your own site a lot)
- Events and search tracking that merges keystrokes into one search
- Overview and Searches reports, plus room for your own tabs

## Installation

Requires PHP 8.2+, Laravel 12 and `livewire/flux` ^2.1.

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/stefanstifter/laravel-site-analytics" }
]
```

```bash
composer require stifter/site-analytics:^0.2
php artisan migrate
php artisan vendor:publish --tag=site-analytics-config   # optional
```

With Tailwind, add `@source '../../vendor/stifter/site-analytics/resources/views';` to your CSS.

## Usage

Track routes with the middleware (inside the `web` group):

```php
Route::middleware('site-analytics')->group(function () {
    Route::view('/', 'welcome');
});
```

Record events:

```php
app(EventRecorder::class)->record($request, 'outbound_click', $model, ['url' => $url]);
app(EventRecorder::class)->recordSearch($request, 'listing', $term, $filters, $resultCount);
```

Reports live at `/dashboard/analytics`. To add your own tab, register a route and add it to `navigation`:

```php
'navigation' => [
    ['label' => 'Overview', 'route' => 'admin.analytics.overview'],
    ['label' => 'Races', 'route' => 'admin.analytics.races'],
],
```

Build the page with `<x-site-analytics::layout>`, `stats`, `stat` and `panel` components so it fits in.

## Configuration

The usual suspects in `config/site-analytics.php`: `enabled` (env `ANALYTICS_ENABLED`), `exclude`, `ignored_ips`, `ignore_robots`, `ignore_authenticated`, plus route, layout, navigation and retention settings. Each one is documented in the file.

## Upgrading from 0.1

0.2 stands on its own feet. Existing data and migrations stay untouched.

1. `composer require stifter/site-analytics:^0.2` (andreaselia/analytics and friends leave quietly).
2. Move your settings from `config/analytics.php` to `config/site-analytics.php` (`ignoredIPs` → `ignored_ips`, `ignoreRobots` → `ignore_robots`, `exclude` should list your admin paths), then delete the old file.
3. Change `middleware('analytics')` to `middleware('site-analytics')`. The old alias works for one more release.
4. Replace `AndreasElia\Analytics\Models\PageView` with `Stifter\SiteAnalytics\Models\PageView`, including in migrations.
5. Drop tests for `RequestSessionProvider`, `analytics.session.provider` and the `/analytics` redirect. They are gone.

`country` is no longer filled. It was the browser language all along, which is a fun fact but not a country.

## Thanks

This package started life on top of [andreaselia/laravel-analytics](https://github.com/andreaselia/laravel-analytics) by Andreas Elia and Tom Irons. It showed the way; we just packed lighter. Thank you!

## License

MIT
