<?php

namespace App\Actions;

use App\Models\Goal;
use App\Models\Household;
use Illuminate\Support\Facades\DB;

class SaveGoal
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Household $household, array $data): Goal
    {
        return DB::transaction(function () use ($household, $data) {
            $goal = $household->goals()->create([
                'name' => $data['name'],
                'target_amount' => (int) $data['target_amount'],
                'target_date' => $data['target_date'] ?? null,
                'sort' => $data['sort'] ?? ((int) $household->goals()->max('sort') + 1),
            ]);

            $this->syncSweepRules($goal, $data['sweep_budget_ids'] ?? []);

            return $goal;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Goal $goal, array $data): Goal
    {
        return DB::transaction(function () use ($goal, $data) {
            $goal->update([
                'name' => $data['name'],
                'target_amount' => (int) $data['target_amount'],
                'target_date' => $data['target_date'] ?? null,
                'sort' => $data['sort'] ?? $goal->sort,
            ]);

            if (array_key_exists('sweep_budget_ids', $data)) {
                $this->syncSweepRules($goal, $data['sweep_budget_ids'] ?? []);
            }

            return $goal;
        });
    }

    /** @param  array<int, mixed>  $budgetIds */
    private function syncSweepRules(Goal $goal, array $budgetIds): void
    {
        $budgetIds = array_values(array_unique(array_map('intval', $budgetIds)));

        if ($budgetIds === []) {
            $goal->sweepRules()->delete();

            return;
        }

        $goal->sweepRules()->whereNotIn('budget_id', $budgetIds)->delete();

        $existing = $goal->sweepRules()->pluck('budget_id')->all();

        foreach (array_diff($budgetIds, $existing) as $budgetId) {
            $goal->sweepRules()->create(['budget_id' => $budgetId]);
        }
    }
}
