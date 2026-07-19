<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    protected $model = News::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => ['ru' => $title, 'tg' => $title, 'en' => ''],
            'summary' => ['ru' => fake()->sentence(), 'tg' => fake()->sentence(), 'en' => ''],
            'body' => ['ru' => fake()->paragraphs(3, true), 'tg' => fake()->paragraph(), 'en' => ''],
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'status' => ContentStatus::Draft,
            'show_on_home' => true,
            'views_count' => fake()->numberBetween(0, 9000),
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
