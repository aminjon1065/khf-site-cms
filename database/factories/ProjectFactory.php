<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => ['ru' => $title, 'tg' => $title, 'en' => ''],
            'summary' => ['ru' => fake()->sentence(), 'tg' => fake()->sentence(), 'en' => ''],
            'body' => ['ru' => fake()->paragraph(), 'tg' => '', 'en' => ''],
            'status' => ContentStatus::Draft,
            'lifecycle_status' => fake()->randomElement(ProjectStatus::cases()),
            'years' => '2026–2030',
            'customer' => 'КЧС Республики Таджикистан',
            'partner' => fake()->company(),
            'budget' => fake()->numberBetween(1, 30).' млн долл. США',
            'goals' => ['ru' => [fake()->sentence()]],
            'timeline' => [['date' => 'Июнь 2026', 'text' => fake()->sentence(), 'tone' => 'success']],
            'direction' => ['address' => 'г. Душанбе', 'phone' => '+992 (37) 221-59-00', 'email' => 'info@khf.tj'],
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
