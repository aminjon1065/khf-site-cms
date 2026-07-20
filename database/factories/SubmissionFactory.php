<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'topic' => fake()->randomElement(['Вопрос о деятельности Комитета', 'Жалоба', 'Предложение']),
            'message' => fake()->paragraph(),
            'consent' => true,
            'status' => SubmissionStatus::New,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (test)',
        ];
    }
}
