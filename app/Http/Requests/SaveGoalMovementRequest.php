<?php

namespace App\Http\Requests;

use App\Enums\GoalMovementTypeEnum;
use App\Http\Requests\Concerns\ResolvesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGoalMovementRequest extends FormRequest
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
            'type' => ['required', Rule::enum(GoalMovementTypeEnum::class)],
            'budget_id' => [
                'nullable',
                'integer',
                Rule::exists('budgets', 'id')
                    ->where('household_id', $this->household()->id)
                    ->whereNull('archived_at'),
            ],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:200'],
            'occurred_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
