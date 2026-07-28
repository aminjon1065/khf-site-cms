<?php

namespace Database\Factories;

use App\Models\UsabilitySession;
use App\Support\UsabilityStudy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsabilitySession>
 */
class UsabilitySessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tasks = [];

        foreach (UsabilityStudy::TASKS as $key => $definition) {
            $tasks[$key] = [
                'completed' => true,
                'assisted' => false,
                'duration_seconds' => $definition['target_seconds'] === null
                    ? fake()->numberBetween(30, 240)
                    : max(30, $definition['target_seconds'] - 60),
                'irreversible_error' => false,
            ];
        }
        $susResponses = [5, 1, 5, 1, 5, 1, 5, 1, 5, 1];

        return [
            'participant_code' => 'P-'.Str::upper(Str::random(8)),
            'role' => fake()->randomElement(['editor', 'regional_editor', 'alert_operator']),
            'experience_level' => fake()->randomElement(['none', 'basic', 'experienced']),
            'tasks' => $tasks,
            'sus_responses' => $susResponses,
            'sus_score' => UsabilityStudy::susScore($susResponses),
            'notes' => null,
            'facilitator_id' => null,
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
        ];
    }
}
