<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesHousehold;
use App\Models\Goal;
use App\Models\GoalSweepRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveGoalRequest extends FormRequest
{
    use ResolvesHousehold;

    public function authorize(): bool
    {
        return $this->user()->can('edit', $this->household());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'target_amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'target_date' => ['nullable', 'date'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'sweep_budget_ids' => ['sometimes', 'array', 'max:30'],
            'sweep_budget_ids.*' => [
                'integer',
                Rule::exists('budgets', 'id')
                    ->where('household_id', $this->household()->id)
                    ->whereNull('archived_at')
                    ->where('repeat_monthly', true),
            ],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $budgetIds = array_map(
                'intval',
                (array) $this->input('sweep_budget_ids', [])
            );

            if ($budgetIds === []) {
                return;
            }

            $goal = $this->route('goal');
            $goalId = $goal instanceof Goal ? $goal->id : null;

            $claimed = GoalSweepRule::query()
                ->whereIn('budget_id', $budgetIds)
                ->when($goalId !== null, fn ($q) => $q->where('goal_id', '!=', $goalId))
                ->exists();

            if ($claimed) {
                $validator->errors()->add(
                    'sweep_budget_ids',
                    'That budget is already sweeping into another goal.'
                );
            }
        }];
    }
}
