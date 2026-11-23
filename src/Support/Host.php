<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Host names as the reports show them: lowercase, without "www.".
 */
final class Host
{
    public static function normalize(string|false|null $host): ?string
    {
        if (! is_string($host) || $host === '') {
            return null;
        }

        return Str::of($host)->lower()->chopStart('www.')->toString();
    }

    /**
     * Whether a normalized host is this site: the requested host or the one in app.url.
     */
    public static function isOwn(string $host, Request $request): bool
    {
        return in_array($host, [
            self::normalize($request->getHost()),
            self::normalize(parse_url((string) config('app.url'), PHP_URL_HOST)),
        ], true);
    }
}
