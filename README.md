# Site Analytics for Laravel

Minimal, privacy-friendly site analytics for Stifter Software Laravel apps, built on top of [andreaselia/analytics](https://github.com/andreaselia/laravel-analytics).

- Page views with hashed session IDs (never raw) and a cookie-free daily visitor ID
- Crawlers and, by default, logged-in users are not tracked
- Generic events (`analytics_events`) with an optional subject model and JSON properties
- Search tracking that merges a visitor's keystrokes into one search
- Admin report pages (Overview, Searches) built with Flux, extendable with your own tabs

## Requirements

- PHP 8.2+
- Laravel 12
- `andreaselia/analytics` ^2.4
- `livewire/flux` ^2.1

## Installation

Add the GitHub repository to the app's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/stefanstifter/laravel-site-analytics" }
]
```

```bash
composer require stifter/site-analytics
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

## Configuration

See `config/site-analytics.php`:

- `routes` – prefix, route name prefix and middleware of the report pages (default `dashboard/analytics`, `admin.analytics.`)
- `layout` – Blade layout component the report pages render in (default `layouts.app`)
- `navigation` – tabs shown on every report page; add your own report pages here
- `ignore_authenticated` – skip page views and events of logged-in users
- `search_refine_seconds` – window in which refined searches update the previous one
- `retention_days` – per event name, how long events are kept
- `prune_lottery` – chance that recording an event prunes expired ones (no scheduler needed)

## Recording events

```php
use Stifter\SiteAnalytics\EventRecorder;

app(EventRecorder::class)->record($request, 'outbound_click', $model, ['url' => $url]);

app(EventRecorder::class)->recordSearch($request, 'listing', $term, $filters, $resultCount);
```

## Custom report pages

Use the package's Blade components to match the built-in pages:

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

Then add the route to `navigation` in the published config.

## License

MIT
