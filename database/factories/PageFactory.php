<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => ['ru' => $title, 'tg' => $title, 'en' => ''],
            'body' => ['ru' => fake()->paragraph(), 'tg' => fake()->paragraph(), 'en' => ''],
            'status' => ContentStatus::Draft,
            'parent_id' => null,
            'sort' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);
    }
}
