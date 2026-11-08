<?php

namespace Database\Factories;

use App\Enums\GoalMovementTypeEnum;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoalMovement> */
class GoalMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'household_id' => fn (array $a) => Goal::query()->whereKey($a['goal_id'])->value('household_id'),
            'user_id' => User::factory(),
            'type' => GoalMovementTypeEnum::CONTRIBUTION,
            'amount' => 500_000,
            'note' => null,
            'occurred_at' => '2026-10-15 12:00:00',
            'period_start' => '2026-10-01',
        ];
    }
}
