<?php

namespace Database\Factories;

use App\Enums\ProgramType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProgramFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->unique()->words(3, true);

        return [
            'slug'        => Str::slug($title) . '-' . Str::random(6),
            'title'       => ucwords($title),
            'description' => $this->faker->paragraph(),
            'type'        => $this->faker->randomElement(ProgramType::cases())->value,
            'is_active'   => true,
            'starts_at'   => now()->subMonth(),
            'ends_at'     => now()->addMonths(6),
            'created_by'  => User::factory(),
        ];
    }
}
