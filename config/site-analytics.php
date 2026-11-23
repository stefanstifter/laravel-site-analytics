<?php

return [

    /**
     * Admin report pages: URL prefix, route name prefix, middleware and the Blade layout component they render in.
     */
    'routes' => [
        'prefix' => 'dashboard/analytics',
        'name' => 'admin.analytics.',
        'middleware' => ['web', 'auth', 'verified'],
    ],

    'layout' => 'layouts.app',

    /**
     * Tabs shown on every analytics page, in order. Apps can add their own report pages here; tabs whose route does not exist are skipped.
     */
    'navigation' => [
        ['label' => 'Overview', 'route' => 'admin.analytics.overview'],
        ['label' => 'Searches', 'route' => 'admin.analytics.searches'],
    ],

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
     */
    'prune_lottery' => [1, 100],

];
