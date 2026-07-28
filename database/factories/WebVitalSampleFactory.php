<?php

namespace Database\Factories;

use App\Models\WebVitalSample;
use App\Support\WebVitalThresholds;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebVitalSample>
 */
class WebVitalSampleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metric = WebVitalThresholds::METRICS[fake()->numberBetween(0, count(WebVitalThresholds::METRICS) - 1)];
        $value = match ($metric) {
            'LCP' => fake()->randomFloat(2, 500, 5000),
            'INP' => fake()->randomFloat(2, 20, 700),
            'CLS' => fake()->randomFloat(4, 0, 0.5),
        };

        return [
            'metric' => $metric,
            'value' => $value,
            'rating' => WebVitalThresholds::rating($metric, $value),
            'route' => '/ru/news',
            'locale' => 'ru',
            'device' => fake()->randomElement(['mobile', 'tablet', 'desktop']),
            'navigation_type' => 'navigate',
            'sample_hash' => hash('sha256', Str::uuid()->toString()),
        ];
    }

    public function metric(string $metric, float $value): static
    {
        return $this->state(fn (): array => [
            'metric' => $metric,
            'value' => $value,
            'rating' => WebVitalThresholds::rating($metric, $value),
        ]);
    }
}
