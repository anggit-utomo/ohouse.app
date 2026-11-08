<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Goal> */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->unique()->words(2, true),
            'target_amount' => 5_000_000,
            'target_date' => null,
            'sort' => 0,
        ];
    }
}
