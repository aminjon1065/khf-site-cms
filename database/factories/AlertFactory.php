<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\HazardType;
use App\Enums\Severity;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'internal_title' => $title.' — служебное',
            'title' => ['ru' => $title, 'tg' => $title, 'en' => $title],
            'summary' => ['ru' => fake()->sentence(), 'tg' => fake()->sentence(), 'en' => ''],
            'body' => ['ru' => fake()->paragraph(), 'tg' => fake()->paragraph(), 'en' => ''],
            'instructions' => ['ru' => fake()->sentence(), 'tg' => fake()->sentence(), 'en' => ''],
            'contacts' => ['ru' => '112', 'tg' => '112', 'en' => '112'],
            'hazard_type' => fake()->randomElement(HazardType::cases()),
            'severity' => fake()->randomElement(Severity::cases()),
            'status' => ContentStatus::Draft,
            'territory_type' => 'regions',
            'source' => 'Оперативная служба КЧС',
            'channels' => ['site', 'sos_app'],
            'starts_at' => now(),
            'ends_at' => now()->addDays(3),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn () => ['severity' => Severity::Critical]);
    }
}
