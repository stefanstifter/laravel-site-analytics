<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\Tests\Concerns\UsesCountryDatabase;
use Stifter\SiteAnalytics\Tests\TestCase;

class SystemPageTest extends TestCase
{
    use RefreshDatabase;
    use UsesCountryDatabase;

    private const URL = '/dashboard/analytics/system';

    protected function defineEnvironment($app): void
    {
        $app['config']->set('site-analytics.routes.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCountryDatabase();
        $this->travelTo('2026-11-23 10:00:00');
        $this->actingAs(new User);
        Http::preventStrayRequests();
    }

    public function test_it_shows_what_is_tracked_without_a_period_picker(): void
    {
        config(['site-analytics.track_group' => 'web', 'site-analytics.exclude' => ['admin/*']]);
        PageView::factory()->create(['created_at' => now()->subMinutes(3)]);

        $response = $this->get(self::URL)->assertOk();

        $response->assertSee('data-last-page-view>3 minutes ago', false)
            ->assertSee('Every page of the web middleware group')
            ->assertSee('admin/*')
            ->assertSee('Your own visits are not counted while you are logged in.')
            ->assertDontSee('data-period-picker', false);
        $this->assertMatchesRegularExpression('/data-tab="admin\.analytics\.system"\s+aria-current="page"/', $response->getContent());
    }

    public function test_the_reports_link_to_the_system_page(): void
    {
        $this->get('/dashboard/analytics')
            ->assertOk()
            ->assertSee('href="http://localhost/dashboard/analytics/system"', false);
    }

    public function test_without_a_database_it_explains_and_offers_the_install(): void
    {
        $this->get(self::URL)
            ->assertOk()
            ->assertSee('data-geoip-state="missing"', false)
            ->assertSee('It is not installed yet, so no countries are recorded.')
            ->assertSee('Install now')
            ->assertSee('php artisan site-analytics:geoip-update');
    }

    public function test_with_a_database_it_shows_its_age_the_callers_country_and_the_coverage(): void
    {
        $this->installFixtureDatabase();
        PageView::factory()->create(['country_code' => 'AT']);
        PageView::factory()->create(['country_code' => null]);
        PageView::factory()->create(['country_code' => null, 'created_at' => now()->subDays(8)]);

        $this->withServerVariables(['REMOTE_ADDR' => '81.2.69.160'])
            ->get(self::URL)
            ->assertOk()
            ->assertSee('data-geoip-state="ok"', false)
            ->assertSee('data-geoip-built-at="2026-10-22"', false)
            ->assertSee('81.2.69.160 → 🇬🇧 United Kingdom')
            ->assertSee('data-geoip-coverage="50"', false)
            ->assertSee('Update now');
    }

    public function test_an_old_database_is_flagged_as_outdated(): void
    {
        $this->installFixtureDatabase();
        $this->travelTo('2026-12-10');

        $this->get(self::URL)
            ->assertSee('data-geoip-state="outdated"', false)
            ->assertSee('The database is older than 35 days.');
    }

    public function test_a_private_address_hints_at_trust_proxies(): void
    {
        $this->installFixtureDatabase();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->get(self::URL)
            ->assertSee('10.0.0.5 → Unknown')
            ->assertSee('Configure TrustProxies');
    }

    public function test_update_now_installs_the_database(): void
    {
        Http::fake(['download.db-ip.com/*' => Http::response($this->gzippedFixtureDatabase())]);

        $this->post(self::URL.'/geoip')
            ->assertRedirect(self::URL)
            ->assertSessionHas('site-analytics.flash', ['type' => 'success', 'message' => 'Installed the country database from Oct 22, 2026.']);

        $this->get(self::URL)->assertSee('data-geoip-state="ok"', false);
    }

    public function test_a_failed_update_shows_the_error(): void
    {
        Http::fake(['download.db-ip.com/*' => Http::response('Oops', 500)]);

        $this->followingRedirects()
            ->post(self::URL.'/geoip')
            ->assertOk()
            ->assertSee('data-flash="error"', false)
            ->assertSee('The update failed: Downloading https://download.db-ip.com/free/dbip-country-lite-2026-11.mmdb.gz failed with HTTP 500.')
            ->assertSee('data-geoip-error', false);
    }

    public function test_it_shows_stored_data_and_how_long_it_is_kept(): void
    {
        config(['site-analytics.retention_days' => ['search' => 365]]);
        PageView::factory()->create(['created_at' => '2026-03-14 12:00:00']);
        PageView::factory()->create();
        AnalyticsEvent::factory()->count(2)->create(['name' => 'search']);
        AnalyticsEvent::factory()->create(['name' => 'outbound_click']);

        $this->get(self::URL)
            ->assertOk()
            ->assertSee('data-page-view-count="2"', false)
            ->assertSee('since Mar 14, 2026, kept until deleted')
            ->assertSee('data-event="search" data-event-count="2"', false)
            ->assertSee('kept 365 days')
            ->assertSee('data-event="outbound_click" data-event-count="1"', false);
    }

    public function test_prune_now_deletes_expired_events(): void
    {
        config(['site-analytics.retention_days' => ['search' => 30]]);
        AnalyticsEvent::factory()->create(['name' => 'search', 'created_at' => now()->subDays(31)]);
        $recent = AnalyticsEvent::factory()->create(['name' => 'search']);

        $this->post(self::URL.'/prune')
            ->assertRedirect(self::URL)
            ->assertSessionHas('site-analytics.flash', ['type' => 'success', 'message' => 'Deleted 1 expired event.']);

        $this->assertSame([$recent->id], AnalyticsEvent::query()->pluck('id')->all());
    }

    public function test_the_manage_gate_keeps_viewers_from_running_actions(): void
    {
        Gate::define('manageSiteAnalytics', fn (User $user): bool => false);
        Http::fake();
        $event = AnalyticsEvent::factory()->create(['name' => 'search', 'created_at' => now()->subYears(2)]);

        $this->get(self::URL)
            ->assertOk()
            ->assertDontSee('data-action=', false)
            ->assertSee('php artisan site-analytics:geoip-update')
            ->assertSee('php artisan site-analytics:prune');
        $this->post(self::URL.'/geoip')->assertForbidden();
        $this->post(self::URL.'/prune')->assertForbidden();

        Http::assertNothingSent();
        $this->assertModelExists($event);
    }
}
