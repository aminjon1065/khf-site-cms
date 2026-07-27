<?php

namespace Database\Factories;

use App\Models\Leader;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leader>
 */
class LeaderFactory extends Factory
{
    protected $model = Leader::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = fake()->jobTitle();
        $name = fake()->name();

        return [
            'role' => ['ru' => $role, 'tg' => $role, 'en' => ''],
            'name' => ['ru' => $name, 'tg' => $name, 'en' => ''],
            'meta' => ['ru' => fake()->sentence(3), 'tg' => '', 'en' => ''],
            'bio' => ['ru' => fake()->sentence(), 'tg' => '', 'en' => ''],
            'is_chairman' => false,
            'sort' => fake()->numberBetween(0, 100),
        ];
    }

    public function chairman(): self
    {
        return $this->state(fn (): array => ['is_chairman' => true, 'sort' => 0]);
    }
}
