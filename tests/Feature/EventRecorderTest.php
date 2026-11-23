<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Stifter\SiteAnalytics\EventRecorder;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Tests\TestCase;

class EventRecorderTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    private const CRAWLER_USER_AGENT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    public function test_it_records_an_event_with_hashed_session_and_device(): void
    {
        $request = $this->request(self::BROWSER_USER_AGENT);

        $event = app(EventRecorder::class)->record($request, AnalyticsEvent::OUTBOUND_CLICK, properties: ['url' => 'https://example.com']);

        $this->assertNotNull($event);
        $this->assertSame(hash('sha256', $request->session()->getId()), $event->session);
        $this->assertSame('phone', $event->device);
        $this->assertSame(['url' => 'https://example.com'], $event->fresh()->properties);
    }

    public function test_it_skips_crawlers(): void
    {
        $this->assertNull(app(EventRecorder::class)->record($this->request(self::CRAWLER_USER_AGENT), AnalyticsEvent::OUTBOUND_CLICK));
    }

    public function test_it_skips_everything_when_disabled(): void
    {
        config()->set('site-analytics.enabled', false);

        $this->assertNull(app(EventRecorder::class)->record($this->request(self::BROWSER_USER_AGENT), AnalyticsEvent::OUTBOUND_CLICK));
    }

    public function test_it_skips_logged_in_users_unless_configured(): void
    {
        $request = $this->request(self::BROWSER_USER_AGENT);
        $request->setUserResolver(fn (): User => new User);

        $this->assertNull(app(EventRecorder::class)->record($request, AnalyticsEvent::OUTBOUND_CLICK));

        config()->set('site-analytics.ignore_authenticated', false);

        $this->assertNotNull(app(EventRecorder::class)->record($request, AnalyticsEvent::OUTBOUND_CLICK));
    }

    private function request(string $userAgent): Request
    {
        $request = Request::create('/races/x', server: ['HTTP_USER_AGENT' => $userAgent]);
        $request->setLaravelSession(tap(new Store('test', new ArraySessionHandler(10)))->start());

        return $request;
    }
}
