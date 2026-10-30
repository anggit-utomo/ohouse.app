<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Budget> */
class BudgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->unique()->word(),
            'default_amount' => 500_000,
            'repeat_monthly' => true,
            'sort' => 0,
        ];
    }
}
