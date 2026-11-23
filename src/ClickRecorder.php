<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stifter\SiteAnalytics\Detection\UserAgent;
use Stifter\SiteAnalytics\GeoIp\CountryDatabase;
use Stifter\SiteAnalytics\Models\Click;
use Stifter\SiteAnalytics\Session\SessionHash;
use Stifter\SiteAnalytics\Support\Host;
use Stifter\SiteAnalytics\Visitor\VisitorIdentifier;

/**
 * Records clicks on tracked links and buttons, under the same rules as page views and events.
 */
final class ClickRecorder
{
    public const GROUP_LENGTH = 100;

    public const LABEL_LENGTH = 150;

    public const HREF_LENGTH = 1024;

    private const URI_LENGTH = 255;

    public function __construct(
        private readonly EventRecorder $events,
        private readonly VisitorIdentifier $visitorIdentifier,
        private readonly CountryDatabase $countries,
    ) {}

    /**
     * Record a click.
     *
     * @param  string|null  $href  Where the link goes, absolute or relative. Null for buttons.
     * @param  string|null  $page  The path of the page the click happened on. Defaults to the request's own path.
     */
    public function record(Request $request, string $label, ?string $group = null, ?string $href = null, ?string $page = null): ?Click
    {
        $agent = UserAgent::fromRequest($request);
        $uri = $page === null ? $this->path($request, $request->getPathInfo()) : $this->path($request, $page);
        $label = $this->clean($label, self::LABEL_LENGTH);

        if ($label === null || ! $this->shouldTrack($request, $agent, $uri)) {
            return null;
        }

        [$href, $targetHost] = $this->destination($request, $href);

        $click = Click::query()->create([
            'group_name' => $this->clean($group, self::GROUP_LENGTH),
            'label' => $label,
            'href' => $href,
            'target_host' => $targetHost,
            'uri' => $uri,
            'visitor' => $this->visitorIdentifier->identify($request),
            'session' => SessionHash::fromRequest($request),
            'device' => $agent->device(),
            'country_code' => $this->countries->country($request->ip()),
        ]);

        $this->pruneExpiredByLottery();

        return $click;
    }

    /**
     * Delete clicks older than clicks.retention_days. Null keeps them.
     */
    public function pruneExpired(): int
    {
        $days = config('site-analytics.clicks.retention_days');

        if ($days === null) {
            return 0;
        }

        return Click::query()->where('created_at', '<', CarbonImmutable::now()->subDays((int) $days))->delete();
    }

    public function shouldTrack(Request $request, UserAgent $agent, string $uri): bool
    {
        return config('site-analytics.clicks.enabled', true)
            && $this->events->shouldTrack($request, $agent)
            && ! $this->isExcluded($uri);
    }

    /**
     * Whether clicks on this page are left out by the "exclude" config, like its page views.
     */
    private function isExcluded(string $uri): bool
    {
        $path = $uri === '/' ? '/' : trim($uri, '/');

        foreach (config('site-analytics.exclude', []) as $pattern) {
            $pattern = $pattern === '/' ? $pattern : trim((string) $pattern, '/');

            if (Str::is($pattern, $path) || Str::is($pattern, url($uri))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The page path as page views store it: relative to the app's root, without query string or trailing slash.
     */
    private function path(Request $request, string $page): string
    {
        $path = (string) parse_url($page, PHP_URL_PATH);
        $path = Str::chopStart($path, $request->getBaseUrl());

        return mb_substr('/'.trim($path, '/'), 0, self::URI_LENGTH);
    }

    /**
     * The stored href and the other site's host. Links within the site keep only their path, so
     * http://, https:// and www. variants count as one link. Anything but web, mailto: and tel: links is dropped.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function destination(Request $request, ?string $href): array
    {
        $href = trim((string) $href);

        if ($href === '') {
            return [null, null];
        }

        if (preg_match('#^(mailto|tel):#i', $href) === 1) {
            return [mb_substr($href, 0, self::HREF_LENGTH), null];
        }

        if (preg_match('#^https?://#i', $href) !== 1) {
            return str_starts_with($href, '/') && ! str_starts_with($href, '//')
                ? [mb_substr($href, 0, self::HREF_LENGTH), null]
                : [null, null];
        }

        $host = Host::normalize(parse_url($href, PHP_URL_HOST));

        if ($host === null) {
            return [null, null];
        }

        if (Host::isOwn($host, $request)) {
            $relative = preg_replace('#^https?://[^/?\#]+#i', '', $href) ?: '/';

            return [mb_substr(str_starts_with($relative, '/') ? $relative : '/'.$relative, 0, self::HREF_LENGTH), null];
        }

        return [mb_substr($href, 0, self::HREF_LENGTH), $host];
    }

    private function clean(?string $value, int $length): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    private function pruneExpiredByLottery(): void
    {
        [$hits, $outOf] = config('site-analytics.prune_lottery', [1, 100]);

        if (random_int(1, max(1, (int) $outOf)) <= (int) $hits) {
            $this->pruneExpired();
        }
    }
}
