# Site Analytics for Laravel

Privacy-friendly page view and event tracking for Laravel, with report pages that bring their own styles. No cookies, no raw session IDs, no 40 MB JavaScript tracker. Just a middleware and a couple of tables.

- Page views with hashed sessions and a cookie-free daily visitor ID
- Bots and logged-in users are ignored by default (you know you visit your own site a lot)
- Events and search tracking that merges keystrokes into one search
- Overview and Searches reports in English and German, plus room for your own tabs

## Installation

Requires PHP 8.2+ and Laravel 12 or 13. No Tailwind, Flux or asset build needed.

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/stefanstifter/laravel-site-analytics" }
]
```

```bash
composer require stefanstifter/site-analytics:^0.3
php artisan site-analytics:install
```

## Usage

Record every page of the `web` group in `config/site-analytics.php`, keeping admin pages out:

```php
'track_group' => 'web',
'exclude' => ['admin', 'admin/*', 'login'],
```

Or only some routes:

```php
Route::middleware('site-analytics')->group(function () {
    Route::view('/', 'welcome');
});
```

Either way, only successful HTML responses to GET requests count. Redirects, errors, JSON and prefetches don't.

Record events:

```php
use Stifter\SiteAnalytics\Facades\SiteAnalytics;

SiteAnalytics::event('outbound_click', $model, ['url' => $url]);
SiteAnalytics::search('listing', $term, $filters, $resultCount);
```

## Reports

Reports live at `/dashboard/analytics`. Any logged-in user can see them until you say otherwise:

```php
Gate::define('viewSiteAnalytics', fn (User $user) => $user->is_admin);
```

They render as a standalone page. To show them inside your app, set `layout` to a Blade component such as `'layouts.app'` (it gets a `title` and the page as its slot). Set `dark_mode` to `'class'` if your app switches dark mode with a `.dark` class only, as Flux does. Colours are CSS variables, so `.sa { --sa-accent: #e11d48; }` makes them yours.

To add your own tab, register a route and add it to `navigation`:

```php
'navigation' => [
    ['label' => 'Overview', 'route' => 'admin.analytics.overview'],
    ['label' => 'Races', 'route' => 'admin.analytics.races'],
],
```

Build the page with `<x-site-analytics::layout>` and the `stats`, `stat`, `panel`, `ranked-list`, `bar` and `table` components so it fits in. Tracking only? Set `routes.enabled` to `false`.

## Configuration

The usual suspects in `config/site-analytics.php`: `enabled` (env `ANALYTICS_ENABLED`), `track_group`, `exclude`, `ignored_ips`, `ignore_robots`, `ignore_authenticated`, plus route, layout, navigation and retention settings. Each one is documented in the file.

Expired events are pruned now and then while recording. To prune on a schedule instead, run `site-analytics:prune` daily and set `prune_lottery` to `[0, 1]`.

## Upgrading from 0.2

1. Flux is no longer used. Drop the `@source` line for this package from your CSS.
2. Set `layout` to your app layout (it used to default to `layouts.app`), and `dark_mode` to `'class'` with Flux.
3. `verified` is no longer in the default route middleware, and the `viewSiteAnalytics` gate now guards the reports. Define it if not every user is an admin.
4. The `analytics` middleware alias is gone. Use `site-analytics`.
5. Only successful HTML responses are recorded, so redirects and 404s no longer show up as page views.

## Thanks

This package started life on top of [andreaselia/laravel-analytics](https://github.com/andreaselia/laravel-analytics). It showed the way. Thank you!

## License

MIT
