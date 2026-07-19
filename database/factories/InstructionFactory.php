<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\HazardType;
use App\Models\Instruction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instruction>
 */
class InstructionFactory extends Factory
{
    protected $model = Instruction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ['ru' => $name, 'tg' => $name, 'en' => ''],
            'summary' => ['ru' => fake()->sentence(), 'tg' => fake()->sentence(), 'en' => ''],
            'hazard_type' => fake()->randomElement(HazardType::cases()),
            'sections' => [
                'before' => ['ru' => [fake()->sentence()]],
                'during' => ['ru' => [fake()->sentence()]],
                'after' => ['ru' => [fake()->sentence()]],
                'prohibited' => ['ru' => [fake()->sentence()]],
            ],
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
