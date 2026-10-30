<?php

namespace Database\Factories;

use App\Enums\TransactionTypeEnum;
use App\Models\BudgetMonth;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'budget_month_id' => BudgetMonth::factory(),
            'household_id' => fn (array $a) => BudgetMonth::query()->findOrFail($a['budget_month_id']),
            'user_id' => User::factory(),
            'type' => TransactionTypeEnum::EXPENSE,
            'amount' => 50_000,
            'note' => fake()->sentence(3),
            'occurred_at' => '2026-10-15 12:00:00',
        ];
    }

    public function type(TransactionTypeEnum $type): static
    {
        return $this->state(['type' => $type]);
    }
}
