<?php

namespace Stifter\SiteAnalytics;

use AndreasElia\Analytics\Models\PageView;
use AndreasElia\Analytics\RequestSessionProvider;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stifter\SiteAnalytics\Observers\PageViewObserver;
use Stifter\SiteAnalytics\Session\HashedSessionProvider;

class SiteAnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/site-analytics.php', 'site-analytics');
    }

    public function boot(): void
    {
        $this->configureTrackingDefaults();

        PageView::observe(PageViewObserver::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'site-analytics');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'site-analytics');

        Route::prefix(config('site-analytics.routes.prefix'))
            ->name(config('site-analytics.routes.name'))
            ->middleware(config('site-analytics.routes.middleware'))
            ->group(__DIR__.'/../routes/web.php');

        $this->app->booted(fn () => $this->replaceBaseDashboard());

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/site-analytics.php' => config_path('site-analytics.php'),
            ], 'site-analytics-config');
        }
    }

    /**
     * Never store raw session IDs: swap andreaselia's default session provider for the hashed one, while respecting any other custom provider.
     */
    public function configureTrackingDefaults(): void
    {
        if (config('analytics.session.provider', RequestSessionProvider::class) === RequestSessionProvider::class) {
            config()->set('analytics.session.provider', HashedSessionProvider::class);
        }
    }

    /**
     * Send andreaselia/analytics' own dashboard to the Overview page, so there is only one analytics area.
     */
    private function replaceBaseDashboard(): void
    {
        Route::middleware(config('site-analytics.routes.middleware'))->group(function (): void {
            Route::get(trim((string) config('analytics.prefix'), '/').'/{view?}', RedirectController::class)
                ->defaults('destination', '/'.trim(config('site-analytics.routes.prefix'), '/'))
                ->defaults('status', 302)
                ->where('view', '(.*)')
                ->name('analytics');
        });
    }
}
