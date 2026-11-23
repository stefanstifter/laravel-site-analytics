<?php

namespace Stifter\SiteAnalytics\Observers;

use AndreasElia\Analytics\Models\PageView;
use Illuminate\Support\Str;
use Stifter\SiteAnalytics\Visitor\VisitorIdentifier;

class PageViewObserver
{
    public function __construct(private readonly VisitorIdentifier $visitorIdentifier) {}

    /**
     * Skip logged-in users (when configured), tag the page view with the daily visitor ID, and drop referrers from our own site so "Sources" only lists where visitors arrived from.
     */
    public function creating(PageView $pageView): ?bool
    {
        if (config('site-analytics.ignore_authenticated') && auth()->check()) {
            return false;
        }

        if ($pageView->visitor === null && app()->bound('request')) {
            $pageView->visitor = $this->visitorIdentifier->identify(request());
        }

        if ($pageView->source !== null && $this->isOwnHost($pageView->source, $pageView->host)) {
            $pageView->source = null;
        }

        return null;
    }

    public function isOwnHost(string $source, ?string $requestHost): bool
    {
        $ownHosts = array_filter([
            $this->normalizeHost($requestHost),
            $this->normalizeHost(parse_url((string) config('app.url'), PHP_URL_HOST)),
        ]);

        return in_array($this->normalizeHost($source), $ownHosts, true);
    }

    private function normalizeHost(?string $host): ?string
    {
        if (blank($host)) {
            return null;
        }

        return Str::of($host)->lower()->chopStart('www.')->toString();
    }
}
