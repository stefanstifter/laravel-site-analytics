<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Stifter\SiteAnalytics\Tests\TestCase;

class ReportRoutesDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('site-analytics.routes.enabled', false);
    }

    public function test_the_report_routes_can_be_switched_off(): void
    {
        $this->assertFalse(Route::has('admin.analytics.overview'));
    }
}
