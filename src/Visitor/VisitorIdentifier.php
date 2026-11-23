<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Visitor;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Identifies a visitor for one day without cookies: a hash of IP and user agent with a random salt that rotates daily.
 * Old salts expire from the cache, after which a stored ID can no longer be linked back to an IP address.
 */
final class VisitorIdentifier
{
    private const SALT_LIFETIME_HOURS = 48;

    public function __construct(private readonly Repository $cache) {}

    public function identify(Request $request): string
    {
        return hash('sha256', implode('|', [
            $this->saltFor(CarbonImmutable::now()),
            (string) $request->ip(),
            (string) $request->userAgent(),
        ]));
    }

    private function saltFor(CarbonImmutable $moment): string
    {
        return $this->cache->remember(
            'site-analytics:visitor-salt:'.$moment->toDateString(),
            $moment->addHours(self::SALT_LIFETIME_HOURS),
            fn (): string => Str::random(64),
        );
    }
}
