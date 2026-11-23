<?php

return [

    /**
     * Record page views and events at all. Set ANALYTICS_ENABLED=false to switch tracking off, e.g. in tests.
     */
    'enabled' => env('ANALYTICS_ENABLED', true),

    /**
     * Middleware group whose pages are all recorded, e.g. 'web'. Null records only routes using the "site-analytics" middleware.
     * Either way only successful HTML responses to GET requests count; use "exclude" to leave out admin and login pages.
     */
    'track_group' => null,

    /**
     * Paths not recorded as page views, as accepted by $request->is(), e.g. 'dashboard' and 'dashboard/*'. Full URLs work too.
     */
    'exclude' => [],

    /**
     * IP addresses whose page views are not recorded.
     */
    'ignored_ips' => [],

    /**
     * Do not record page views from crawlers and other bots. Events are never recorded for bots.
     */
    'ignore_robots' => true,

    /**
     * Admin report pages: whether to register them at all, URL prefix, route name prefix and middleware.
     * Access is also checked with the "viewSiteAnalytics" gate, which lets in any logged-in user unless the app defines it:
     * Gate::define('viewSiteAnalytics', fn (User $user) => $user->is_admin);
     */
    'routes' => [
        'enabled' => true,
        'prefix' => 'dashboard/analytics',
        'name' => 'admin.analytics.',
        'middleware' => ['web', 'auth'],
    ],

    /**
     * Blade component the report pages render in, e.g. 'layouts.app'. It receives a "title" attribute and the page as its slot.
     * Null renders a standalone page. The reports bring their own styles, so the app needs no Tailwind or Flux setup.
     */
    'layout' => null,

    /**
     * How the reports switch to dark mode. 'media' follows the system setting, unless an ancestor has .dark, .light or data-theme="dark|light".
     * 'class' only goes dark when an ancestor has .dark or data-theme="dark", which is how Flux does it.
     */
    'dark_mode' => 'media',

    /**
     * The app's own report tabs, shown after the package's Overview and Searches tabs. Tabs whose route does not exist are skipped.
     * E.g. ['label' => 'Races', 'route' => 'admin.analytics.races'].
     */
    'extra_navigation' => [],

    /**
     * Do not record page views or events for logged-in users (typically the site's own admins).
     */
    'ignore_authenticated' => true,

    /**
     * Searches by the same visitor within this many seconds, whose term extends or shortens the previous one, update that search instead of adding a new one (typing "tra", "trans", "transcon" is one search).
     */
    'search_refine_seconds' => 10,

    /**
     * Events older than this many days are deleted, per event name. Names not listed are kept.
     */
    'retention_days' => [
        'search' => 365,
    ],

    /**
     * Chance that recording an event also deletes expired events: [hits, out of]. Works without a scheduler.
     * If you schedule "site-analytics:prune" instead, set this to [0, 1].
     */
    'prune_lottery' => [1, 100],

];
