<?php

namespace App\Actions;

use App\Enums\HouseholdRoleEnum;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateHousehold
{
    /** @param list<string> $budgetNames starter budgets (amounts are set on the next screen) */
    public function handle(User $user, string $name, string $currency = 'IDR', int $monthStartDay = 1, array $budgetNames = []): Household
    {
        return DB::transaction(function () use ($user, $name, $currency, $monthStartDay, $budgetNames) {
            $household = Household::create([
                'name' => $name,
                'currency' => $currency,
                'month_start_day' => $monthStartDay,
                'owner_id' => $user->id,
                'period_start' => now()->toDateString(),
            ]);

            $household->members()->attach($user->id, ['role' => HouseholdRoleEnum::OWNER->value]);

            $period = now()->toDateString();

            foreach (array_values($budgetNames) as $i => $budgetName) {
                $budget = $household->budgets()->create([
                    'name' => $budgetName,
                    'default_amount' => 0,
                    'repeat_monthly' => true,
                    'sort' => $i,
                ]);
                $budget->months()->create(['period_start' => $period, 'allocated' => 0, 'carried_in' => 0]);
            }

            $user->forceFill(['current_household_id' => $household->id])->save();

            return $household;
        });
    }
}
