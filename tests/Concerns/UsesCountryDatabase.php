<?php

namespace Stifter\SiteAnalytics\Tests\Concerns;

use Illuminate\Support\Facades\File;
use Stifter\SiteAnalytics\GeoIp\CountryDatabase;

/**
 * Points the country database at an empty temporary directory. The fixture is MaxMind's GeoIP2-Country-Test.mmdb
 * (github.com/maxmind/MaxMind-DB, MIT/Apache-2.0), built 2026-10-22, e.g. 81.2.69.160 = GB, 89.160.20.112 = SE, 2a02:cf40::1 = NO.
 */
trait UsesCountryDatabase
{
    protected const FIXTURE_DATABASE = __DIR__.'/../Fixtures/GeoIP2-Country-Test.mmdb';

    protected string $geoIpDirectory;

    protected function setUpCountryDatabase(): void
    {
        $this->geoIpDirectory = sys_get_temp_dir().'/site-analytics-'.bin2hex(random_bytes(6));
        config(['site-analytics.geoip.database' => $this->geoIpDirectory.'/countries.mmdb']);

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->geoIpDirectory));
    }

    protected function installFixtureDatabase(): void
    {
        File::ensureDirectoryExists($this->geoIpDirectory);
        File::copy(self::FIXTURE_DATABASE, $this->geoIpDirectory.'/countries.mmdb');
        $this->app->make(CountryDatabase::class)->forget();
    }

    protected function gzippedFixtureDatabase(): string
    {
        return (string) gzencode((string) file_get_contents(self::FIXTURE_DATABASE));
    }
}
