<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetMonth;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetMonth> */
class BudgetMonthFactory extends Factory
{
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'period_start' => '2026-10-01',
            'allocated' => 1_000_000,
            'carried_in' => 0,
        ];
    }
}
