<?php

namespace Stifter\SiteAnalytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Stifter\SiteAnalytics\Models\Click;

/**
 * @extends Factory<Click>
 */
class ClickFactory extends Factory
{
    protected $model = Click::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_name' => null,
            'label' => fake()->words(2, true),
            'href' => '/'.fake()->slug(2),
            'target_host' => null,
            'uri' => '/',
            'visitor' => hash('sha256', fake()->uuid()),
            'session' => hash('sha256', fake()->uuid()),
            'device' => fake()->randomElement(['desktop', 'phone', 'tablet']),
            'country_code' => null,
        ];
    }

    /**
     * A click on a link to another site.
     */
    public function outbound(string $url = 'https://example.com/'): static
    {
        return $this->state(fn (): array => [
            'href' => $url,
            'target_host' => preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)),
        ]);
    }
}
