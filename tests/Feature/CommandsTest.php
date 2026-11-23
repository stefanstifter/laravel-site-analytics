<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Tests\TestCase;

class CommandsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_install_publishes_the_config_and_lists_the_next_steps(): void
    {
        $published = config_path('site-analytics.php');
        File::delete($published);

        try {
            $this->artisan('site-analytics:install')
                ->expectsConfirmation('Run the migrations now?', 'no')
                ->expectsOutputToContain('viewSiteAnalytics')
                ->assertSuccessful();

            $this->assertFileEquals(__DIR__.'/../../config/site-analytics.php', $published);
        } finally {
            File::delete($published);
        }
    }
}
