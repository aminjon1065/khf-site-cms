<?php

namespace Database\Factories;

use App\Models\EditorialRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EditorialRevision>
 */
class EditorialRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_type' => 'news',
            'content_id' => null,
            'user_id' => User::factory(),
            'draft_key' => (string) Str::uuid(),
            'data' => ['title' => ['ru' => fake()->sentence()]],
            'base_version' => null,
            'source' => 'autosave',
        ];
    }
}
