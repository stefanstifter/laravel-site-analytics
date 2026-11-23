<?php

use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;
use Stifter\SiteAnalytics\Http\Controllers\OverviewController;
use Stifter\SiteAnalytics\Http\Controllers\SearchesController;
use Stifter\SiteAnalytics\Http\Controllers\SystemController;
use Stifter\SiteAnalytics\SiteAnalyticsServiceProvider;

Route::get('/', OverviewController::class)->name('overview');
Route::get('searches', SearchesController::class)->name('searches');
Route::get('users', RedirectController::class)
    ->defaults('destination', '/'.trim(config('site-analytics.routes.prefix'), '/'))
    ->defaults('status', 302)
    ->name('users');

Route::get('system', [SystemController::class, 'show'])->name('system');
Route::middleware('can:'.SiteAnalyticsServiceProvider::MANAGE_GATE)->group(function (): void {
    Route::post('system/geoip', [SystemController::class, 'updateCountryDatabase'])->name('system.geoip');
});
