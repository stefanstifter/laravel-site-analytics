<?php

namespace Stifter\SiteAnalytics;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stifter\SiteAnalytics\Http\Middleware\RecordPageView;

class SiteAnalyticsServiceProvider extends ServiceProvider
{
    private static ?string $css = null;

    /**
     * The report stylesheet, inlined by the layout so apps need no asset build or published files.
     */
    public static function css(): string
    {
        return self::$css ??= (string) file_get_contents(__DIR__.'/../resources/css/site-analytics.css');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/site-analytics.php', 'site-analytics');
    }

    public function boot(): void
    {
        $this->registerMiddleware();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'site-analytics');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'site-analytics');

        Route::prefix(config('site-analytics.routes.prefix'))
            ->name(config('site-analytics.routes.name'))
            ->middleware(config('site-analytics.routes.middleware'))
            ->group(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/site-analytics.php' => config_path('site-analytics.php'),
            ], 'site-analytics-config');
        }
    }

    /**
     * Register the page view middleware as "site-analytics". The "analytics" alias keeps routes written for andreaselia/analytics working and will be removed in the next release.
     */
    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('site-analytics', RecordPageView::class);
        $router->aliasMiddleware('analytics', RecordPageView::class);
    }
}
