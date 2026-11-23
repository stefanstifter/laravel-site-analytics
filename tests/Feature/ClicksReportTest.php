<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Stifter\SiteAnalytics\Models\Click;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\Tests\TestCase;

class ClicksReportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/dashboard/analytics/clicks';

    protected function defineEnvironment($app): void
    {
        $app['config']->set('site-analytics.routes.middleware', ['web']);
    }

    protected function disableClicks($app): void
    {
        $app['config']->set('site-analytics.clicks.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-23 10:00:00');
        $this->actingAs(new User);
    }

    public function test_without_clicks_it_explains_the_setup(): void
    {
        $this->get(self::URL)
            ->assertOk()
            ->assertSee('data-clicks-setup', false)
            ->assertSee('&lt;x-site-analytics::script /&gt;', false)
            ->assertSee('data-track-group="Side projects"', false)
            ->assertSee('Links to other sites, mailto: and tel: are counted without markup.')
            ->assertDontSee('data-summary-clicks', false);
    }

    public function test_it_summarizes_clicks_groups_links_pages_and_destinations(): void
    {
        $this->seedSideProjects();

        $response = $this->get(self::URL)->assertOk();

        $response->assertSee('data-summary-clicks="5"', false)
            ->assertSee('data-summary-clickers="4"', false)
            // 4 of the 5 visitors to /en/projects and / clicked.
            ->assertSee('data-summary-click-rate="80"', false)
            ->assertSee('data-group="Side projects" data-clicks="3" data-visitors="3"', false)
            ->assertSee('data-group="" data-clicks="2" data-visitors="1"', false)
            ->assertSee('data-link="https://agent.example.com/" data-clicks="2" data-visitors="2"', false)
            ->assertSee('data-link="https://github.com/stso" data-clicks="1"', false)
            ->assertSee('data-page="/en/projects" data-clicks="3" data-click-rate="100"', false)
            ->assertSee('data-page="/" data-clicks="2" data-click-rate="50"', false)
            ->assertSee('data-destination="agent.example.com" data-clicks="2"', false)
            ->assertSee('data-destination="github.com" data-clicks="1"', false)
            ->assertSee('data-chart-point="2026-11-23" data-chart-value="5"', false)
            ->assertSee('Ungrouped');

        $this->assertMatchesRegularExpression('/data-tab="admin\.analytics\.clicks"\s+aria-current="page"/', $response->getContent());
    }

    public function test_a_group_shows_only_its_links(): void
    {
        $this->seedSideProjects();

        $this->get(self::URL.'?group='.urlencode('Side projects'))
            ->assertOk()
            ->assertSee('data-summary-clicks="3"', false)
            // 3 of the 3 visitors to /en/projects clicked.
            ->assertSee('data-summary-click-rate="100"', false)
            ->assertSee('<span aria-current="page" data-crumb-group>Side projects</span>', false)
            ->assertDontSee('data-table="groups"', false)
            ->assertSee('data-link="https://agent.example.com/"', false)
            ->assertSee('data-link="/en/assistant"', false)
            ->assertDontSee('data-link="https://github.com/stso"', false);
    }

    public function test_an_empty_group_shows_the_ungrouped_clicks(): void
    {
        $this->seedSideProjects();

        $this->get(self::URL.'?group=')
            ->assertOk()
            ->assertSee('data-summary-clicks="2"', false)
            ->assertSee('data-link="https://github.com/stso"', false)
            ->assertSee('data-link="mailto:hello@stso.test"', false)
            ->assertDontSee('data-link="https://agent.example.com/"', false);
    }

    public function test_a_link_shows_its_clicks_pages_and_destination(): void
    {
        $this->seedSideProjects();

        $this->get(self::URL.'?group='.urlencode('Side projects').'&link='.urlencode('https://agent.example.com/'))
            ->assertOk()
            ->assertSee('data-summary-clicks="2"', false)
            ->assertSee('data-crumb-link>AI Agent</span>', false)
            ->assertSee('https://agent.example.com/ ↗')
            ->assertDontSee('data-table="links"', false)
            ->assertDontSee('data-panel="destinations"', false)
            ->assertSee('data-page="/en/projects" data-clicks="2"', false);
    }

    public function test_buttons_are_told_apart_by_label(): void
    {
        Click::factory()->count(2)->create(['label' => 'Request offer', 'href' => null, 'group_name' => 'Pricing']);
        Click::factory()->create(['label' => 'Compare plans', 'href' => null, 'group_name' => 'Pricing']);

        $this->get(self::URL.'?group=Pricing&link='.urlencode('Request offer'))
            ->assertOk()
            ->assertSee('data-summary-clicks="2"', false);
    }

    public function test_the_period_limits_the_clicks(): void
    {
        $this->seedSideProjects();
        Click::factory()->count(3)->create(['created_at' => now()->subDays(10)]);

        $this->get(self::URL.'?period=7')->assertSee('data-summary-clicks="5"', false);
        $this->get(self::URL.'?period=30')->assertSee('data-summary-clicks="8"', false);
    }

    public function test_the_overview_shows_clicks_once_any_were_recorded(): void
    {
        $this->get('/dashboard/analytics')->assertOk()->assertDontSee('data-summary-clicks', false);

        $this->seedSideProjects();

        $this->get('/dashboard/analytics')->assertOk()->assertSee('data-summary-clicks="5"', false);
    }

    public function test_the_system_page_shows_click_tracking_and_stored_clicks(): void
    {
        $this->seedSideProjects();

        $this->get('/dashboard/analytics/system')
            ->assertOk()
            ->assertSee('data-click-tracking="on"', false)
            ->assertSee('Marked links and buttons, and every link to another site')
            ->assertSee('data-click-count="5"', false)
            ->assertSee('kept 365 days');
    }

    #[DefineEnvironment('disableClicks')]
    public function test_without_click_tracking_there_is_no_clicks_tab(): void
    {
        $this->get('/dashboard/analytics')->assertOk()->assertDontSee('data-tab="admin.analytics.clicks"', false);
        $this->get(self::URL)->assertNotFound();
    }

    /**
     * Five page visitors: three on /en/projects (all clicking a side project) and two on / (one clicking two other links).
     */
    private function seedSideProjects(): void
    {
        foreach (['a', 'b', 'c'] as $visitor) {
            PageView::factory()->create(['uri' => '/en/projects', 'visitor' => $visitor]);
        }
        PageView::factory()->create(['uri' => '/', 'visitor' => 'd']);
        PageView::factory()->create(['uri' => '/', 'visitor' => 'e']);
        PageView::factory()->create(['uri' => '/en/other', 'visitor' => 'f']);

        $sideProject = ['group_name' => 'Side projects', 'uri' => '/en/projects'];
        Click::factory()->outbound('https://agent.example.com/')->create([...$sideProject, 'label' => 'AI Agent', 'visitor' => 'a']);
        Click::factory()->outbound('https://agent.example.com/')->create([...$sideProject, 'label' => 'AI Agent', 'visitor' => 'b']);
        Click::factory()->create([...$sideProject, 'label' => 'Assistant', 'href' => '/en/assistant', 'visitor' => 'c']);
        Click::factory()->outbound('https://github.com/stso')->create(['label' => 'GitHub', 'uri' => '/', 'visitor' => 'd']);
        Click::factory()->create(['label' => 'Email us', 'href' => 'mailto:hello@stso.test', 'uri' => '/', 'visitor' => 'd']);
    }
}
