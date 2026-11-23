# Site Analytics for Laravel

Privacy-friendly page view and event tracking for Laravel, with report pages that bring their own styles. No cookies, no raw session IDs, no 40 MB JavaScript tracker. Just a middleware and a couple of tables.

- Page views with hashed sessions and a cookie-free daily visitor ID
- Visitor countries from a local IP database. IPs never leave the server and are never stored
- Bots and logged-in users are ignored by default (you know you visit your own site a lot)
- Click tracking for links and buttons with a 1 KB inline script and two data attributes, plus every link to another site
- Events and search tracking that merges keystrokes into one search
- Overview, Searches and Clicks reports in English and German, plus room for your own tabs
- A System page that shows what is tracked and keeps the country database up to date

## Installation

Requires PHP 8.2+ and Laravel 12 or 13. No Tailwind, Flux or asset build needed.

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/stefanstifter/laravel-site-analytics" }
]
```

```bash
composer require stefanstifter/site-analytics:^0.4
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

For clicks on links and buttons, see [Clicks](#clicks).

## Clicks

Find out which of your links and offers people actually click. Add the script to the layout of your public pages, once:

```blade
<x-site-analytics::script />
```

Then mark what to count:

```blade
<section data-track-group="Side projects">
    <a href="https://example.com">AI Agent</a>          {{-- "AI Agent" in "Side projects" --}}
    <button>Request offer</button>                       {{-- buttons count too --}}
</section>

<a href="/contact" data-track="Hero CTA">Get in touch</a> {{-- one link, own label --}}
<a href="/pricing" data-track>See pricing</a>             {{-- one link, labelled by its text --}}
<footer data-track="false">…</footer>                    {{-- never counted --}}
```

Every link and button inside a `data-track-group` counts. Labels are the `data-track` value, the `aria-label` or the visible text, without screen-reader-only text. Links to other sites, `mailto:` and `tel:` count without any markup; set `clicks.outbound` to `false` to count only what you marked. Middle-clicks count, and a click survives the browser leaving the page.

The Clicks tab shows clicks per day, groups, the top links, other sites, devices, and each page's click rate: the share of its visitors who clicked. Click a group or link to see only its clicks.

How it works: one click listener sends `group`, `label`, `href` and the page path with `navigator.sendBeacon` to `POST /site-analytics/clicks`. That route runs in the `web` group, without CSRF (beacons can't send the token and cached pages would carry stale ones), limited to 60 clicks per minute per IP. Bots, `ignored_ips`, `exclude`d pages and, by default, logged-in users are not recorded, and for them the script isn't even rendered. Links within your site are stored as paths, so `https://`, `http://` and `www.` variants count as one link. Clicks are kept for `clicks.retention_days` (365).

The script takes your CSP nonce with `nonce="…"`, and uses Vite's nonce when one is set. For clicks the browser can't see, such as a redirect route, record them from PHP:

```php
SiteAnalytics::click('AI Agent', group: 'Side projects', href: $url);
```

## Countries

Countries are looked up in a local copy of [DB-IP](https://db-ip.com)'s free IP-to-country database (about 8 MB, published monthly, CC BY 4.0). Lookups happen on your server; only the country code is stored.

Get the database with `php artisan site-analytics:geoip-update`, or with the button on the System page. Without it, no country is recorded. To keep it fresh, either:

- schedule the command monthly, or
- set `geoip.auto_update` to `true` if you have no cron, as on most shared hosting. A page view after the database turns 35 days old then downloads a new one after the response is sent.

Behind a proxy, load balancer or CDN, configure Laravel's [TrustProxies](https://laravel.com/docs/requests#configuring-trusted-proxies), or every visitor gets the proxy's IP and country. The System page shows which IP and country the app sees for you.

## Reports

Reports live at `/dashboard/analytics`, one menu entry for everything: link to `route('admin.analytics.overview')`. Any logged-in user can see them until you say otherwise:

```php
Gate::define('viewSiteAnalytics', fn (User $user) => $user->is_admin);
```

The System tab shows what is tracked, the country database and the stored data, with buttons to update the database and prune events. Who may click them is up to `manageSiteAnalytics`, which defaults to `viewSiteAnalytics`:

```php
Gate::define('manageSiteAnalytics', fn (User $user) => $user->is_owner);
```

They render as a standalone page. To show them inside your app, set `layout` to a Blade component such as `'layouts.app'` (it gets a `title` and the page as its slot). Set `dark_mode` to `'class'` if your app switches dark mode with a `.dark` class only, as Flux does. Colours are CSS variables, so `.sa { --sa-accent: #e11d48; }` makes them yours.

To add your own tab after Overview, Searches and Clicks, register a route and add it to `extra_navigation`:

```php
'extra_navigation' => [
    ['label' => 'Races', 'route' => 'admin.analytics.races'],
],
```

Build the page with `<x-site-analytics::layout>` and the `stats`, `stat`, `panel`, `ranked-list`, `bar` and `table` components so it fits in. Tracking only? Set `routes.enabled` to `false`.

## Configuration

The usual suspects in `config/site-analytics.php`: `enabled` (env `ANALYTICS_ENABLED`), `track_group`, `exclude`, `ignored_ips`, `ignore_robots`, `ignore_authenticated`, `clicks`, plus route, layout, extra navigation, retention and `geoip` settings. Each one is documented in the file.

Expired events and clicks are pruned now and then while recording. To prune on a schedule instead, run `site-analytics:prune` daily and set `prune_lottery` to `[0, 1]`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Thanks

This package started life on top of [andreaselia/laravel-analytics](https://github.com/andreaselia/laravel-analytics). It showed the way. Thank you!

## License

MIT
