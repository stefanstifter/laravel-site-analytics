<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use Stifter\SiteAnalytics\Facades\SiteAnalytics;
use Stifter\SiteAnalytics\Models\Click;
use Stifter\SiteAnalytics\SiteAnalyticsServiceProvider;
use Stifter\SiteAnalytics\Tests\TestCase;
use Stifter\SiteAnalytics\Visitor\VisitorIdentifier;

class RecordClickTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/site-analytics/clicks';

    private const PHONE_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    private const CRAWLER_USER_AGENT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://stso.test');
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('projects/redirect', function (): string {
            SiteAnalytics::click('AI Agent', 'Side projects', 'https://agent.example.com');

            return 'ok';
        });
    }

    protected function disableClicks($app): void
    {
        $app['config']->set('site-analytics.clicks.enabled', false);
    }

    public function test_it_records_a_click_with_group_destination_page_and_visitor(): void
    {
        $this->click(['label' => 'AI Agent', 'group' => 'Side projects', 'href' => 'https://www.Agent.example.com/start?ref=1', 'page' => '/en/projects'])
            ->assertNoContent();

        $click = Click::query()->sole();

        $this->assertSame('Side projects', $click->group_name);
        $this->assertSame('AI Agent', $click->label);
        $this->assertSame('https://www.Agent.example.com/start?ref=1', $click->href);
        $this->assertSame('agent.example.com', $click->target_host);
        $this->assertSame('/en/projects', $click->uri);
        $this->assertSame('phone', $click->device);
        $this->assertSame(64, strlen((string) $click->visitor));
        $this->assertNotNull($click->session);
        $this->assertNotNull($click->created_at);
    }

    public function test_the_visitor_matches_the_one_page_views_record(): void
    {
        $this->click(['label' => 'Contact', 'page' => '/']);

        $request = request()->duplicate();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', self::PHONE_USER_AGENT);

        $this->assertSame(app(VisitorIdentifier::class)->identify($request), Click::query()->sole()->visitor);
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: string|null}>
     */
    public static function destinations(): array
    {
        return [
            'own site, absolute' => ['https://stso.test/en/assistant?plan=pro#pricing', '/en/assistant?plan=pro#pricing', null],
            'own site, www and http' => ['http://www.stso.test', '/', null],
            'own site, relative' => ['/en/contact', '/en/contact', null],
            'other site' => ['https://github.com/stefanstifter', 'https://github.com/stefanstifter', 'github.com'],
            'email' => ['mailto:hello@stso.test', 'mailto:hello@stso.test', null],
            'phone' => ['tel:+43123456', 'tel:+43123456', null],
            'script' => ['javascript:alert(1)', null, null],
            'protocol relative' => ['//evil.test/x', null, null],
            'button' => [null, null, null],
        ];
    }

    #[DataProvider('destinations')]
    public function test_it_normalizes_where_the_link_goes(?string $href, ?string $storedHref, ?string $targetHost): void
    {
        $this->click(array_filter(['label' => 'Link', 'href' => $href, 'page' => '/']))->assertNoContent();

        $click = Click::query()->sole();
        $this->assertSame($storedHref, $click->href);
        $this->assertSame($targetHost, $click->target_host);
    }

    public function test_it_stores_the_page_like_page_views_do(): void
    {
        $this->click(['label' => 'Link', 'page' => '/en/projects/?utm_source=x']);
        $this->click(['label' => 'Link', 'page' => '/']);

        $this->assertSame(['/en/projects', '/'], Click::query()->orderBy('id')->pluck('uri')->all());
    }

    public function test_it_tidies_whitespace_and_drops_empty_groups(): void
    {
        $this->click(['label' => "  Request\n   offer ", 'group' => '  ', 'page' => '/']);

        $click = Click::query()->sole();
        $this->assertSame('Request offer', $click->label);
        $this->assertNull($click->group_name);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function malformedClicks(): array
    {
        return [
            'no label' => [['page' => '/']],
            'no page' => [['label' => 'Link']],
            'page is a full URL' => [['label' => 'Link', 'page' => 'https://stso.test/']],
            'label too long' => [['label' => str_repeat('a', 151), 'page' => '/']],
            'label is an array' => [['label' => ['a'], 'page' => '/']],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('malformedClicks')]
    public function test_malformed_clicks_are_rejected_without_a_redirect(array $data): void
    {
        $this->click($data)->assertStatus(422)->assertContent('');

        $this->assertSame(0, Click::query()->count());
    }

    public function test_it_needs_no_csrf_token(): void
    {
        $this->click(['label' => 'Link', 'page' => '/'])->assertNoContent();

        $route = Route::getRoutes()->getByName(SiteAnalyticsServiceProvider::CLICK_ROUTE);
        $middleware = app('router')->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware());

        $this->assertContains('Illuminate\Session\Middleware\StartSession', $middleware);
        foreach ($middleware as $class) {
            $this->assertFalse(is_string($class) && class_exists($class) && (is_a($class, PreventRequestForgery::class, true) || is_a($class, ValidateCsrfToken::class, true)), $class.' checks CSRF tokens.');
        }
    }

    public function test_bots_ignored_ips_and_logged_in_users_are_not_recorded(): void
    {
        $this->withHeader('User-Agent', self::CRAWLER_USER_AGENT)->post(self::URL, ['label' => 'Bot', 'page' => '/'])->assertNoContent();

        config(['site-analytics.ignored_ips' => ['127.0.0.1']]);
        $this->click(['label' => 'Ignored IP', 'page' => '/'])->assertNoContent();
        config(['site-analytics.ignored_ips' => []]);

        $this->actingAs(new User);
        $this->click(['label' => 'Admin', 'page' => '/'])->assertNoContent();

        $this->assertSame(0, Click::query()->count());

        config(['site-analytics.ignore_authenticated' => false]);
        $this->click(['label' => 'Admin', 'page' => '/'])->assertNoContent();

        $this->assertSame(['Admin'], Click::query()->pluck('label')->all());
    }

    public function test_clicks_on_excluded_pages_are_not_recorded(): void
    {
        config(['site-analytics.exclude' => ['admin', 'admin/*']]);

        $this->click(['label' => 'Admin', 'page' => '/admin']);
        $this->click(['label' => 'Enquiry', 'page' => '/admin/enquiries/4']);
        $this->click(['label' => 'Home', 'page' => '/']);

        $this->assertSame(['Home'], Click::query()->pluck('label')->all());
    }

    public function test_nothing_is_recorded_while_tracking_is_off(): void
    {
        config(['site-analytics.enabled' => false]);

        $this->click(['label' => 'Link', 'page' => '/'])->assertNoContent();

        $this->assertSame(0, Click::query()->count());
    }

    public function test_each_ip_is_limited_per_minute(): void
    {
        foreach (range(1, SiteAnalyticsServiceProvider::CLICKS_PER_MINUTE) as $attempt) {
            $this->click(['label' => 'Link '.$attempt, 'page' => '/'])->assertNoContent();
        }

        $this->click(['label' => 'One too many', 'page' => '/'])->assertTooManyRequests();
        $this->assertSame(SiteAnalyticsServiceProvider::CLICKS_PER_MINUTE, Click::query()->count());
    }

    public function test_recording_prunes_expired_clicks_by_lottery(): void
    {
        config(['site-analytics.prune_lottery' => [1, 1], 'site-analytics.clicks.retention_days' => 30]);
        $expired = Click::factory()->create(['created_at' => now()->subDays(31)]);

        $this->click(['label' => 'Link', 'page' => '/']);

        $this->assertModelMissing($expired);
        $this->assertSame(1, Click::query()->count());
    }

    public function test_the_facade_records_a_click_on_the_current_page(): void
    {
        $this->withHeader('User-Agent', self::PHONE_USER_AGENT)->get('/projects/redirect')->assertOk();

        $click = Click::query()->sole();
        $this->assertSame(['Side projects', 'AI Agent', 'https://agent.example.com', 'agent.example.com', '/projects/redirect'], [$click->group_name, $click->label, $click->href, $click->target_host, $click->uri]);
    }

    #[DefineEnvironment('disableClicks')]
    public function test_without_click_tracking_there_is_no_endpoint(): void
    {
        $this->assertFalse(Route::has(SiteAnalyticsServiceProvider::CLICK_ROUTE));
        $this->click(['label' => 'Link', 'page' => '/'])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function click(array $data): TestResponse
    {
        return $this->withHeader('User-Agent', self::PHONE_USER_AGENT)->post(self::URL, $data);
    }
}
