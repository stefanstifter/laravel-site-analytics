<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Stifter\SiteAnalytics\GeoIp\CountryDatabase;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Tests\Concerns\UsesCountryDatabase;
use Stifter\SiteAnalytics\Tests\TestCase;

class CommandsTest extends TestCase
{
    use RefreshDatabase;
    use UsesCountryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCountryDatabase();
    }

    public function test_prune_deletes_only_events_past_their_retention(): void
    {
        config(['site-analytics.retention_days' => ['search' => 30]]);

        $expired = AnalyticsEvent::factory()->create(['name' => 'search', 'created_at' => now()->subDays(31)]);
        $recent = AnalyticsEvent::factory()->create(['name' => 'search', 'created_at' => now()->subDays(29)]);
        $unlisted = AnalyticsEvent::factory()->create(['name' => 'outbound_click', 'created_at' => now()->subYears(2)]);

        $this->artisan('site-analytics:prune')
            ->expectsOutputToContain('Deleted 1 expired event.')
            ->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($recent);
        $this->assertModelExists($unlisted);
    }

    public function test_install_offers_to_download_the_country_database(): void
    {
        $published = config_path('site-analytics.php');
        Http::fake(['download.db-ip.com/*' => Http::response($this->gzippedFixtureDatabase())]);

        try {
            $this->artisan('site-analytics:install')
                ->expectsConfirmation('Run the migrations now?', 'no')
                ->expectsConfirmation('Download the country database now? It is about 8 MB, from DB-IP.', 'yes')
                ->expectsOutputToContain('Installed the country database')
                ->assertSuccessful();

            $this->assertTrue(app(CountryDatabase::class)->exists());
        } finally {
            File::delete($published);
        }
    }

    public function test_install_publishes_the_config_and_lists_the_next_steps(): void
    {
        $published = config_path('site-analytics.php');
        File::delete($published);

        try {
            $this->artisan('site-analytics:install')
                ->expectsConfirmation('Run the migrations now?', 'no')
                ->expectsConfirmation('Download the country database now? It is about 8 MB, from DB-IP.', 'no')
                ->expectsOutputToContain('viewSiteAnalytics')
                ->assertSuccessful();

            $this->assertFileEquals(__DIR__.'/../../config/site-analytics.php', $published);
        } finally {
            File::delete($published);
        }
    }
}
