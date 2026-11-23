<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\Tests\TestCase;

class ReportPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('site-analytics.routes.middleware', ['web']);
        $app['view']->addLocation(__DIR__.'/../Fixtures/views');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(new User);
    }

    public function test_guests_are_not_let_in(): void
    {
        $this->app['auth']->forgetGuards();

        $this->get('/dashboard/analytics')->assertForbidden();
    }

    public function test_the_app_decides_who_may_view_the_reports_with_the_gate(): void
    {
        Gate::define('viewSiteAnalytics', fn (User $user): bool => false);

        $this->get('/dashboard/analytics')->assertForbidden();
    }

    public function test_the_overview_renders_as_a_standalone_page_with_its_own_styles(): void
    {
        PageView::factory()->create(['visitor' => 'a', 'uri' => '/pricing', 'source' => 'example.org', 'country_code' => 'AT']);

        $response = $this->get('/dashboard/analytics?period=7');

        $response->assertOk()
            ->assertSee('<!DOCTYPE html>', false)
            ->assertSee('<title>Analytics · Overview', false)
            ->assertSee('data-site-analytics-styles', false)
            ->assertSee('data-selected-period="7"', false)
            ->assertSee('data-ranked-item="/pricing"', false)
            ->assertSee('href="https://example.org"', false)
            ->assertSee('data-ranked-item="AT"', false)
            ->assertSee('<a href="https://db-ip.com"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('<flux:', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-site-analytics-styles'));
    }

    public function test_app_tabs_sit_between_the_reports_and_system_and_keep_the_selected_period(): void
    {
        Route::get('dashboard/analytics/races', fn (): string => 'races')->name('admin.analytics.races');
        config(['site-analytics.extra_navigation' => [
            ['label' => 'Races', 'route' => 'admin.analytics.races'],
            ['label' => 'Gone', 'route' => 'admin.analytics.gone'],
        ]]);

        $response = $this->get('/dashboard/analytics?period=7')->assertOk();

        preg_match_all('/data-tab="([^"]+)"/', $response->getContent(), $tabs);
        $this->assertSame(['admin.analytics.overview', 'admin.analytics.searches', 'admin.analytics.races', 'admin.analytics.system'], $tabs[1]);
        $response->assertSee('href="http://localhost/dashboard/analytics/races?period=7"', false);
    }

    public function test_the_searches_page_renders(): void
    {
        AnalyticsEvent::factory()->create([
            'name' => AnalyticsEvent::SEARCH,
            'properties' => ['context' => 'listing', 'term' => 'alps', 'filters' => ['region' => 'europe'], 'results' => 0],
        ]);

        $this->get('/dashboard/analytics/searches')
            ->assertOk()
            ->assertSee('data-term="alps"', false)
            ->assertSee('sa-badge', false)
            ->assertSee('data-filter-value="Europe"', false);
    }

    public function test_the_reports_are_translated_into_german(): void
    {
        $this->app->setLocale('de');
        PageView::factory()->create(['created_at' => '2026-03-14 12:00:00']);
        $this->travelTo('2026-03-20');

        $this->get('/dashboard/analytics?period=7')
            ->assertOk()
            ->assertSee('Übersicht')
            ->assertSee('Letzte 30 Tage')
            ->assertSee('Seitenaufrufe')
            ->assertSee('14. Mär 2026');
    }

    public function test_the_reports_render_inside_the_configured_app_layout(): void
    {
        config(['site-analytics.layout' => 'layouts.app', 'site-analytics.dark_mode' => 'class']);

        $this->get('/dashboard/analytics')
            ->assertOk()
            ->assertSee('<body data-app-layout>', false)
            ->assertSee('<title>Analytics · Overview</title>', false)
            ->assertSee('data-sa-dark-mode="class"', false)
            ->assertDontSee('<!DOCTYPE html>', false);
    }
}
