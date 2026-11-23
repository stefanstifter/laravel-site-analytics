<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Stifter\SiteAnalytics\Facades\SiteAnalytics;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Tests\TestCase;

class FacadeTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('go', function (): string {
            SiteAnalytics::event('outbound_click', properties: ['url' => 'https://example.com']);
            SiteAnalytics::search('listing', 'alps', ['region' => 'europe', 'month' => ''], 3);

            return 'ok';
        });
    }

    public function test_events_and_searches_are_recorded_for_the_current_request(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36')
            ->get('/go')
            ->assertOk();

        $events = AnalyticsEvent::query()->orderBy('id')->get();

        $this->assertSame(['outbound_click', 'search'], $events->pluck('name')->all());
        $this->assertSame(['url' => 'https://example.com'], $events[0]->properties);
        $this->assertSame(['context' => 'listing', 'term' => 'alps', 'filters' => ['region' => 'europe'], 'results' => 3], $events[1]->properties);
        $this->assertSame($events[0]->visitor, $events[1]->visitor);
        $this->assertNotNull($events[0]->session);
    }
}
