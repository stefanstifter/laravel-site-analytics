<?php

namespace Stifter\SiteAnalytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Stifter\SiteAnalytics\Models\PageView;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    protected $model = PageView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uri' => '/'.fake()->slug(2),
            'session' => hash('sha256', fake()->uuid()),
            'visitor' => hash('sha256', fake()->uuid()),
            'source' => null,
            'browser' => fake()->randomElement(['Chrome', 'Safari', 'Firefox', 'Edge']),
            'device' => fake()->randomElement(['desktop', 'phone', 'tablet']),
            'host' => 'localhost',
        ];
    }
}
