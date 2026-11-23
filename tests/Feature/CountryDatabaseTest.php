<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Stifter\SiteAnalytics\GeoIp\CountryDatabase;
use Stifter\SiteAnalytics\GeoIp\CountryDatabaseUpdater;
use Stifter\SiteAnalytics\Tests\Concerns\UsesCountryDatabase;
use Stifter\SiteAnalytics\Tests\TestCase;

class CountryDatabaseTest extends TestCase
{
    use UsesCountryDatabase;

    private const THIS_MONTH = 'https://download.db-ip.com/free/dbip-country-lite-2026-11.mmdb.gz';

    private const LAST_MONTH = 'https://download.db-ip.com/free/dbip-country-lite-2026-10.mmdb.gz';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCountryDatabase();
        $this->travelTo('2026-11-23 10:00:00');
        Http::preventStrayRequests();
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function addresses(): array
    {
        return [
            'IPv4' => ['81.2.69.160', 'GB'],
            'IPv6' => ['2a02:cf40::1', 'NO'],
            'not in the database' => ['8.8.8.8', null],
            'private' => ['192.168.1.10', null],
            'loopback' => ['127.0.0.1', null],
            'garbage' => ['not-an-ip', null],
            'missing' => [null, null],
        ];
    }

    #[DataProvider('addresses')]
    public function test_it_looks_up_the_country_of_public_addresses(?string $ip, ?string $country): void
    {
        $this->installFixtureDatabase();

        $this->assertSame($country, app(CountryDatabase::class)->country($ip));
    }

    public function test_without_a_usable_file_there_is_no_country_and_no_error(): void
    {
        $database = app(CountryDatabase::class);
        $this->assertFalse($database->exists());
        $this->assertNull($database->country('81.2.69.160'));
        $this->assertTrue($database->isOutdated());

        File::ensureDirectoryExists($this->geoIpDirectory);
        File::put($database->path(), 'not a database');

        $this->assertFalse($database->exists());
        $this->assertNull($database->country('81.2.69.160'));
    }

    public function test_it_is_outdated_once_older_than_max_age_days(): void
    {
        config(['site-analytics.geoip.max_age_days' => 35]);
        $this->installFixtureDatabase();
        $database = app(CountryDatabase::class);

        $this->assertSame('2026-10-22', $database->builtAt()->toDateString());

        $this->travelTo($database->builtAt()->addDays(35)->subMinute());
        $this->assertFalse($database->isOutdated());

        $this->travelTo($database->builtAt()->addDays(35)->addMinute());
        $this->assertTrue($database->isOutdated());
    }

    public function test_the_command_installs_this_months_database(): void
    {
        Http::fake([self::THIS_MONTH => Http::response($this->gzippedFixtureDatabase())]);

        $this->artisan('site-analytics:geoip-update')
            ->expectsOutputToContain('Installed the country database built 2026-10-22')
            ->assertSuccessful();

        $this->assertSame('GB', app(CountryDatabase::class)->country('81.2.69.160'));
        $this->assertSame([
            'updated_at' => '2026-11-23T10:00:00+00:00',
            'last_attempt_at' => '2026-11-23T10:00:00+00:00',
            'last_error' => null,
            'source' => self::THIS_MONTH,
        ], array_map(fn ($value) => $value instanceof CarbonImmutable ? $value->toIso8601String() : $value, app(CountryDatabaseUpdater::class)->status()));
        $this->assertSame(['countries.json', 'countries.mmdb', 'countries.mmdb.lock'], $this->filesInGeoIpDirectory());
    }

    public function test_it_falls_back_to_last_months_database_early_in_the_month(): void
    {
        Http::fake([
            self::THIS_MONTH => Http::response('Not found', 404),
            self::LAST_MONTH => Http::response($this->gzippedFixtureDatabase()),
        ]);

        app(CountryDatabaseUpdater::class)->update();

        $this->assertSame(self::LAST_MONTH, app(CountryDatabaseUpdater::class)->status()['source']);
        $this->assertTrue(app(CountryDatabase::class)->exists());
    }

    /**
     * @return array<string, array{0: \Closure(): PromiseInterface, 1: string}>
     */
    public static function brokenDownloads(): array
    {
        return [
            'server error' => [fn () => Http::response('Oops', 500), 'failed with HTTP 500'],
            'not gzipped' => [fn () => Http::response('<html>'), 'is not a gzip file'],
            'not a database' => [fn () => Http::response(gzencode('nope')), 'is not a country database'],
            'not published' => [fn () => Http::response('Not found', 404), 'DB-IP has no database for this or last month.'],
        ];
    }

    #[DataProvider('brokenDownloads')]
    public function test_a_failed_update_keeps_the_installed_database_and_remembers_the_error(\Closure $response, string $error): void
    {
        $this->installFixtureDatabase();
        Http::fake(['download.db-ip.com/*' => $response()]);

        $this->artisan('site-analytics:geoip-update')
            ->expectsOutputToContain($error)
            ->assertFailed();

        $this->assertSame('GB', app(CountryDatabase::class)->country('81.2.69.160'));
        $this->assertStringContainsString($error, app(CountryDatabaseUpdater::class)->status()['last_error']);
        $this->assertNotContains('countries.mmdb.download', $this->filesInGeoIpDirectory());
    }

    public function test_only_one_update_runs_at_a_time(): void
    {
        Http::fake();
        File::ensureDirectoryExists($this->geoIpDirectory);
        $lock = fopen($this->geoIpDirectory.'/countries.mmdb.lock', 'c');
        flock($lock, LOCK_EX);

        $this->artisan('site-analytics:geoip-update')
            ->expectsOutputToContain('Another update is already running.')
            ->assertFailed();

        Http::assertNothingSent();
        fclose($lock);
    }

    public function test_it_does_not_update_by_itself_unless_auto_update_is_on(): void
    {
        Http::fake();

        app(CountryDatabaseUpdater::class)->updateIfDue();

        Http::assertNothingSent();
    }

    public function test_auto_update_replaces_an_outdated_database(): void
    {
        config(['site-analytics.geoip.auto_update' => true]);
        $this->installFixtureDatabase();
        Http::fake([self::THIS_MONTH => Http::response($this->gzippedFixtureDatabase())]);

        $this->travelTo('2026-11-25');
        app(CountryDatabaseUpdater::class)->updateIfDue();
        Http::assertNothingSent();

        $this->travelTo('2026-11-27');
        app(CountryDatabaseUpdater::class)->updateIfDue();
        Http::assertSent(fn (Request $request): bool => $request->url() === self::THIS_MONTH);
    }

    public function test_auto_update_retries_a_failed_download_a_day_later(): void
    {
        config(['site-analytics.geoip.auto_update' => true]);
        Http::fake(['download.db-ip.com/*' => Http::response('Oops', 500)]);
        $updater = app(CountryDatabaseUpdater::class);

        $updater->updateIfDue();
        $this->travel(23)->hours();
        $updater->updateIfDue();
        Http::assertSentCount(1);

        $this->travel(2)->hours();
        $updater->updateIfDue();
        Http::assertSentCount(2);
    }

    /**
     * @return array<int, string>
     */
    private function filesInGeoIpDirectory(): array
    {
        return collect(File::files($this->geoIpDirectory))->map->getFilename()->sort()->values()->all();
    }
}
