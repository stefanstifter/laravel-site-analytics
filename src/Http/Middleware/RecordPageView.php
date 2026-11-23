<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stifter\SiteAnalytics\Detection\UserAgent;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\Session\SessionHash;
use Stifter\SiteAnalytics\Visitor\VisitorIdentifier;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a page view for GET requests once the response has been built.
 */
final class RecordPageView
{
    private const UTM_PARAMETERS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    private const MAX_LENGTH = 255;

    public function __construct(private readonly VisitorIdentifier $visitorIdentifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $agent = UserAgent::fromRequest($request);

        if ($this->shouldRecord($request, $agent)) {
            $this->record($request, $agent);
        }

        return $response;
    }

    private function shouldRecord(Request $request, UserAgent $agent): bool
    {
        return config('site-analytics.enabled')
            && $request->isMethod('GET')
            && ! in_array($request->ip(), config('site-analytics.ignored_ips', []), true)
            && ! $this->isExcluded($request)
            && ! (config('site-analytics.ignore_robots') && $agent->isRobot())
            && ! (config('site-analytics.ignore_authenticated') && $request->user() !== null);
    }

    private function isExcluded(Request $request): bool
    {
        foreach (config('site-analytics.exclude', []) as $pattern) {
            $pattern = $pattern === '/' ? $pattern : trim($pattern, '/');

            if ($request->fullUrlIs($pattern) || $request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function record(Request $request, UserAgent $agent): void
    {
        PageView::query()->create([
            'uri' => str_replace($request->root(), '', $request->url()) ?: '/',
            'session' => SessionHash::fromRequest($request),
            'visitor' => $this->visitorIdentifier->identify($request),
            'source' => $this->source($request),
            'browser' => $agent->browser(),
            'device' => $agent->device(),
            'host' => $request->getHost(),
            ...$this->utmParameters($request),
        ]);
    }

    /**
     * The referring site's host without "www.", or null when the visitor came from this site. Referrers that are not web URLs, such as android-app://…, are kept as sent.
     */
    private function source(Request $request): ?string
    {
        $referrer = (string) $request->headers->get('referer');

        if ($referrer === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $referrer) !== 1) {
            return mb_substr($referrer, 0, self::MAX_LENGTH);
        }

        $source = $this->normalizeHost(parse_url($referrer, PHP_URL_HOST));

        return $source === null || $this->isOwnHost($source, $request) ? null : $source;
    }

    private function isOwnHost(string $source, Request $request): bool
    {
        return in_array($source, [
            $this->normalizeHost($request->getHost()),
            $this->normalizeHost(parse_url((string) config('app.url'), PHP_URL_HOST)),
        ], true);
    }

    private function normalizeHost(string|false|null $host): ?string
    {
        if (! is_string($host) || $host === '') {
            return null;
        }

        return Str::of($host)->lower()->chopStart('www.')->toString();
    }

    /**
     * @return array<string, string|null>
     */
    private function utmParameters(Request $request): array
    {
        return collect(self::UTM_PARAMETERS)
            ->mapWithKeys(function (string $parameter) use ($request): array {
                $value = $request->query($parameter);

                return [$parameter => is_string($value) && $value !== '' ? mb_substr($value, 0, self::MAX_LENGTH) : null];
            })
            ->all();
    }
}
