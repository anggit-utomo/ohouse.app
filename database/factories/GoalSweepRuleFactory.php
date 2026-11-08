<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Goal;
use App\Models\GoalSweepRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoalSweepRule> */
class GoalSweepRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'budget_id' => fn (array $attributes) => Budget::factory()->create([
                'household_id' => Goal::query()
                    ->whereKey($attributes['goal_id'])
                    ->value('household_id'),
            ])->id,
        ];
    }
}
