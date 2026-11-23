<?php

namespace Stifter\SiteAnalytics;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel as FoundationHttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stifter\SiteAnalytics\Http\Middleware\RecordPageView;

class SiteAnalyticsServiceProvider extends ServiceProvider
{
    /**
     * The gate that decides who may open the report pages.
     */
    public const GATE = 'viewSiteAnalytics';

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

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/site-analytics.php' => config_path('site-analytics.php'),
            ], 'site-analytics-config');
        }
    }

    /**
     * Register the report pages, guarded by the configured middleware and the viewSiteAnalytics gate.
     */
    private function registerRoutes(): void
    {
        if (! config('site-analytics.routes.enabled', true)) {
            return;
        }

        $this->app->booted(function (): void {
            $gate = $this->app->make(Gate::class);

            if (! $gate->has(self::GATE)) {
                $gate->define(self::GATE, fn (Authenticatable $user): bool => true);
            }
        });

        Route::prefix(config('site-analytics.routes.prefix'))
            ->name(config('site-analytics.routes.name'))
            ->middleware([...config('site-analytics.routes.middleware', []), 'can:'.self::GATE])
            ->group(__DIR__.'/../routes/web.php');
    }

    /**
     * Register the page view middleware as "site-analytics", and add it to the configured middleware group. The "analytics" alias keeps routes written for andreaselia/analytics working and will be removed in the next release.
     */
    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('site-analytics', RecordPageView::class);
        $router->aliasMiddleware('analytics', RecordPageView::class);

        if (! is_string($group = config('site-analytics.track_group')) || $group === '') {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof FoundationHttpKernel) {
            $kernel->appendMiddlewareToGroup($group, RecordPageView::class);
        } else {
            $router->pushMiddlewareToGroup($group, RecordPageView::class);
        }
    }
}
