<?php

namespace Database\Factories;

use App\Enums\CallStatus;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CallFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->unique()->words(4, true);

        return [
            'program_id'    => Program::factory(),
            'slug'          => Str::slug($title) . '-' . Str::random(6),
            'title'         => ucwords($title),
            'description'   => $this->faker->paragraph(),
            'status'        => CallStatus::Open->value,
            'opens_at'      => now()->subWeek(),
            'closes_at'     => now()->addMonths(2),
            'min_team_size' => 1,
            'max_team_size' => 5,
            'created_by'    => User::factory(),
        ];
    }
}
