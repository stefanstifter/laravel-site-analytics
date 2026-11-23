<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\Tests\TestCase;

class TrackGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('site-analytics.track_group', 'web');
        $app['config']->set('site-analytics.exclude', ['login']);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('pricing', fn (): string => 'pricing');
            Route::get('login', fn (): string => 'login');
            Route::middleware('site-analytics')->get('both', fn (): string => 'both');
        });

        Route::get('api-ish', fn (): string => 'outside the group');
    }

    public function test_every_page_in_the_group_is_recorded_once_without_route_middleware(): void
    {
        $this->get('/pricing')->assertOk();
        $this->get('/both')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/api-ish')->assertOk();

        $this->assertSame(['/pricing', '/both'], PageView::query()->orderBy('id')->pluck('uri')->all());
    }
}
