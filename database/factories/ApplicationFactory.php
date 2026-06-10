<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Call;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_id'         => (string) Str::ulid(),
            'call_id'           => Call::factory(),
            'user_id'           => User::factory(),
            'team_id'           => null,
            'status'            => ApplicationStatus::Draft->value,
            'title'             => $this->faker->sentence(5),
            'description'       => $this->faker->paragraph(),
            'problem_statement' => $this->faker->paragraph(),
            'proposed_solution' => $this->faker->paragraph(),
            'submitted_at'      => null,
            'decided_at'        => null,
            'is_archived'       => false,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status'       => ApplicationStatus::Submitted->value,
            'submitted_at' => now(),
        ]);
    }
}
