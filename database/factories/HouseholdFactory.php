<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'currency' => 'IDR',
            'month_start_day' => 1,
            'owner_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn(Household $h) => $h->members()->syncWithoutDetaching([$h->owner_id => ['role' => 'owner']]));
    }
}
