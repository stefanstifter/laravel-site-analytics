<?php

namespace Stifter\SiteAnalytics\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Stifter\SiteAnalytics\SiteAnalyticsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SiteAnalyticsServiceProvider::class];
    }
}
