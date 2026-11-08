<?php

namespace App\Actions;

use App\Models\Goal;
use App\Models\Household;

class SaveGoal
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Household $household, array $data): Goal
    {
        return $household->goals()->create([
            'name' => $data['name'],
            'target_amount' => (int) $data['target_amount'],
            'target_date' => $data['target_date'] ?? null,
            'sort' => $data['sort'] ?? ((int) $household->goals()->max('sort') + 1),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Goal $goal, array $data): Goal
    {
        $goal->update([
            'name' => $data['name'],
            'target_amount' => (int) $data['target_amount'],
            'target_date' => $data['target_date'] ?? null,
            'sort' => $data['sort'] ?? $goal->sort,
        ]);

        return $goal;
    }
}
