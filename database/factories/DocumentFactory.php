<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\DocType;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->sentence(5);

        return [
            'name' => ['ru' => $name, 'tg' => $name, 'en' => ''],
            'doc_type' => fake()->randomElement(DocType::cases()),
            'number' => (string) fake()->numberBetween(1, 2000),
            'doc_date' => fake()->dateTimeBetween('-1 year'),
            'status' => ContentStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => ContentStatus::Published]);
    }
}
