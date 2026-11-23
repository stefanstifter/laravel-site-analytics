<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Stifter\SiteAnalytics\Http\Controllers\OverviewController;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\Tests\TestCase;

class OverviewReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_visitors_page_views_devices_and_sources(): void
    {
        PageView::factory()->create(['visitor' => 'a', 'device' => 'desktop', 'uri' => '/', 'source' => 'google.com']);
        PageView::factory()->create(['visitor' => 'a', 'device' => 'desktop', 'uri' => '/about']);
        PageView::factory()->create(['visitor' => 'b', 'device' => 'phone', 'uri' => '/']);
        PageView::factory()->create(['visitor' => null, 'session' => 'legacy', 'device' => 'tablet', 'uri' => '/']);
        PageView::factory()->create(['visitor' => 'c', 'device' => 'robot', 'uri' => '/']);
        PageView::factory()->create(['visitor' => 'd', 'device' => 'desktop', 'uri' => '/', 'created_at' => now()->subDays(40)]);

        $data = app(OverviewController::class)(Request::create('/'))->getData();

        $this->assertSame(3, $data['summary']['total_visitors']);
        $this->assertSame(4, $data['summary']['page_views']);
        $this->assertSame(
            ['desktop' => 1, 'phone' => 1, 'tablet' => 1],
            $data['devices']->pluck('visitors', 'type')->sortKeys()->all(),
        );
        $this->assertSame(['/' => 3, '/about' => 1], $data['pages']->pluck('visitors', 'label')->all());
        $this->assertSame(['google.com' => 1], $data['referrers']->pluck('visitors', 'label')->all());
    }
}
