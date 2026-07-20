<?php

namespace Database\Factories;

use App\Enums\AnnouncementKind;
use App\Enums\ContentStatus;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(5);

        return [
            'title' => ['ru' => $title, 'tg' => $title, 'en' => ''],
            'body' => ['ru' => fake()->sentence(), 'tg' => fake()->sentence(), 'en' => ''],
            'kind' => fake()->randomElement(AnnouncementKind::cases()),
            'org' => fake()->company(),
            'deadline' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'status' => ContentStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);
    }
}
