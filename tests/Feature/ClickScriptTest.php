<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Stifter\SiteAnalytics\Tests\TestCase;

class ClickScriptTest extends TestCase
{
    protected function disableClicks($app): void
    {
        $app['config']->set('site-analytics.clicks.enabled', false);
    }

    public function test_it_renders_an_inline_script_posting_to_the_relative_endpoint(): void
    {
        $html = Blade::render('<x-site-analytics::script />');

        $this->assertStringContainsString('<script data-site-analytics="clicks">', $html);
        $this->assertStringContainsString("const endpoint = '\\/site-analytics\\/clicks';", $html);
        $this->assertStringContainsString('const outbound = true;', $html);
        $this->assertStringContainsString('navigator.sendBeacon(endpoint, body)', $html);
    }

    public function test_outbound_tracking_can_be_switched_off(): void
    {
        config(['site-analytics.clicks.outbound' => false]);

        $this->assertStringContainsString('const outbound = false;', Blade::render('<x-site-analytics::script />'));
    }

    public function test_it_passes_a_csp_nonce_through(): void
    {
        $this->assertStringContainsString('<script data-site-analytics="clicks" nonce="abc123">', Blade::render('<x-site-analytics::script nonce="abc123" />'));
    }

    public function test_it_renders_nothing_for_logged_in_users_whose_clicks_are_ignored(): void
    {
        $this->actingAs(new User);

        $this->assertSame('', trim(Blade::render('<x-site-analytics::script />')));

        config(['site-analytics.ignore_authenticated' => false]);

        $this->assertStringContainsString('<script', Blade::render('<x-site-analytics::script />'));
    }

    public function test_it_renders_nothing_while_tracking_is_off(): void
    {
        config(['site-analytics.enabled' => false]);

        $this->assertSame('', trim(Blade::render('<x-site-analytics::script />')));
    }

    #[DefineEnvironment('disableClicks')]
    public function test_it_renders_nothing_without_click_tracking(): void
    {
        $this->assertSame('', trim(Blade::render('<x-site-analytics::script />')));
    }
}
