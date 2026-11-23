<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Stifter\SiteAnalytics\Models\PageView;
use Stifter\SiteAnalytics\Tests\TestCase;
use Stifter\SiteAnalytics\Visitor\VisitorIdentifier;

class RecordPageViewTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    private const CRAWLER_USER_AGENT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    protected function defineRoutes($router): void
    {
        Route::middleware(['web', 'site-analytics'])->group(function (): void {
            Route::get('/', fn (): string => 'home');
            Route::get('about', fn (): string => 'about');
            Route::post('about', fn (): string => 'posted');
            Route::get('admin/races', fn (): string => 'admin');
            Route::get('old', fn () => redirect('/'));
            Route::get('missing', fn () => abort(404));
            Route::get('feed.json', fn (): array => ['items' => []]);
            Route::get('sitemap.xml', fn () => response('<urlset/>', 200, ['Content-Type' => 'application/xml']));
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('User-Agent', self::PHONE_USER_AGENT);
    }

    public function test_it_records_a_page_view_with_all_fields(): void
    {
        $this->get('/about?utm_source=newsletter&utm_medium=email&utm_campaign=spring&utm_term=ultra&utm_content=header&other=1')->assertOk();

        $pageView = PageView::query()->sole();
        $this->assertSame('/about', $pageView->uri);
        $this->assertSame(hash('sha256', session()->getId()), $pageView->session);
        $this->assertSame(app(VisitorIdentifier::class)->identify(request()), $pageView->visitor);
        $this->assertSame('phone', $pageView->device);
        $this->assertSame('Safari', $pageView->browser);
        $this->assertSame('localhost', $pageView->host);
        $this->assertNull($pageView->source);
        $this->assertNull($pageView->getAttribute('country'));
        $this->assertSame(
            ['newsletter', 'email', 'spring', 'ultra', 'header'],
            [$pageView->utm_source, $pageView->utm_medium, $pageView->utm_campaign, $pageView->utm_term, $pageView->utm_content],
        );
    }

    public function test_root_is_recorded_as_slash(): void
    {
        $this->get('/')->assertOk();

        $this->assertSame('/', PageView::query()->sole()->uri);
    }

    public function test_utm_values_are_cut_to_255_characters(): void
    {
        $this->get('/about?utm_campaign='.urlencode(str_repeat('ü', 300)).'&utm_source[]=array')->assertOk();

        $pageView = PageView::query()->sole();
        $this->assertSame(str_repeat('ü', 255), $pageView->utm_campaign);
        $this->assertNull($pageView->utm_source);
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function referrers(): array
    {
        return [
            'external site' => ['https://www.google.com/search?q=ultra', 'google.com'],
            'external site with digits and port' => ['https://t3n.de:443/news', 't3n.de'],
            'own request host' => ['http://localhost/races/x', null],
            'app' => ['android-app://com.google.android.gm/', 'android-app://com.google.android.gm/'],
        ];
    }

    #[DataProvider('referrers')]
    public function test_referrer_is_stored_as_source_host(string $referrer, ?string $source): void
    {
        $this->withHeader('Referer', $referrer)->get('/about')->assertOk();

        $this->assertSame($source, PageView::query()->sole()->source);
    }

    public function test_referrers_from_the_app_domain_are_dropped_with_or_without_www_and_in_any_case(): void
    {
        config()->set('app.url', 'https://example.com');

        foreach (['https://www.example.com/', 'https://EXAMPLE.com/races/x', 'https://example.com'] as $referrer) {
            $this->withHeader('Referer', $referrer)->get('http://www.example.com/about')->assertOk();
            $this->withHeader('Referer', $referrer)->get('http://example.com/about')->assertOk();
        }

        $this->assertSame(6, PageView::query()->whereNull('source')->count());
    }

    public function test_it_skips_when_disabled(): void
    {
        config()->set('site-analytics.enabled', false);

        $this->get('/about')->assertOk();

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_it_skips_redirects_errors_and_responses_other_than_html(): void
    {
        $this->get('/old')->assertRedirect('/');
        $this->get('/missing')->assertNotFound();
        $this->get('/feed.json')->assertOk();
        $this->get('/sitemap.xml')->assertOk();

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_it_skips_browser_prefetches(): void
    {
        $this->withHeader('Sec-Purpose', 'prefetch;prerender')->get('/about')->assertOk();

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_it_skips_requests_other_than_get(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/about')
            ->assertOk();

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_it_skips_ignored_ips(): void
    {
        config()->set('site-analytics.ignored_ips', ['10.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->get('/about')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get('/about')->assertOk();

        $this->assertSame(1, PageView::query()->count());
    }

    public function test_it_skips_excluded_paths(): void
    {
        config()->set('site-analytics.exclude', ['/admin', '/admin/*', 'http://localhost/about']);

        $this->get('/admin/races')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/')->assertOk();

        $this->assertSame(['/'], PageView::query()->pluck('uri')->all());
    }

    public function test_it_skips_robots(): void
    {
        $this->withHeader('User-Agent', self::CRAWLER_USER_AGENT)->get('/about')->assertOk();

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_it_records_robots_as_robot_device_when_configured(): void
    {
        config()->set('site-analytics.ignore_robots', false);

        $this->withHeader('User-Agent', self::CRAWLER_USER_AGENT)->get('/about')->assertOk();

        $this->assertSame('robot', PageView::query()->sole()->device);
    }

    public function test_it_skips_logged_in_users(): void
    {
        $this->actingAs(new User)->get('/about')->assertOk();

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_it_records_logged_in_users_when_configured(): void
    {
        config()->set('site-analytics.ignore_authenticated', false);

        $this->actingAs(new User)->get('/about')->assertOk();

        $this->assertSame(1, PageView::query()->count());
    }
}
