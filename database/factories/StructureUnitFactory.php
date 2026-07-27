<?php

namespace Database\Factories;

use App\Models\StructureUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StructureUnit>
 */
class StructureUnitFactory extends Factory
{
    protected $model = StructureUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'num' => str_pad((string) fake()->numberBetween(1, 99), 2, '0', STR_PAD_LEFT),
            'name' => ['ru' => $name, 'tg' => $name, 'en' => ''],
            'desc' => ['ru' => fake()->sentence(), 'tg' => '', 'en' => ''],
            'sort' => fake()->numberBetween(0, 100),
        ];
    }
}
