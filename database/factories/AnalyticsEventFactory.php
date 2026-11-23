<?php

namespace Stifter\SiteAnalytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Stifter\SiteAnalytics\Models\AnalyticsEvent;

/**
 * @extends Factory<AnalyticsEvent>
 */
class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'outbound_click',
            'visitor' => hash('sha256', fake()->uuid()),
            'session' => hash('sha256', fake()->uuid()),
            'device' => fake()->randomElement(['desktop', 'phone', 'tablet']),
            'properties' => null,
        ];
    }
}
