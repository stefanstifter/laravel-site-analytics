# Changelog

All notable changes to this package. It follows [Semantic Versioning](https://semver.org); until 1.0, minor versions may break things.

## 0.3.0 - 2026-11-23

### Added

- Laravel 13 support, tested on PHP 8.2 to 8.5 with Laravel 12 and 13.
- `track_group` config to record every page of a middleware group such as `web`, without route middleware.
- `viewSiteAnalytics` gate guarding the reports. Any logged-in user passes until the app defines it.
- `routes.enabled` config to switch the report pages off and only track.
- `SiteAnalytics` facade: `SiteAnalytics::event()` and `SiteAnalytics::search()` record for the current request.
- `site-analytics:install` and `site-analytics:prune` commands.
- German translations of the reports. Dates follow the app locale.
- `dark_mode` config and `--sa-*` CSS variables to fit the reports into the app's look.
- `table` component for custom report pages.

### Changed

- The Composer package is now `stefanstifter/site-analytics`. The PHP namespace stays `Stifter\SiteAnalytics`.
- The reports no longer need Flux, Livewire or Tailwind. They bring their own scoped stylesheet.
- `layout` defaults to `null`, a standalone page. Set it to your layout component, e.g. `'layouts.app'`, to embed the reports.
- `verified` is no longer in the default route middleware.
- Only successful HTML responses to GET requests are recorded as page views. Redirects, errors, JSON, XML and prefetches are not.

### Removed

- The `analytics` middleware alias. Use `site-analytics`.
- The `livewire/flux` dependency. Drop the `@source` line for this package from your CSS.

## 0.1.0 - 0.2.0

First prototype.
