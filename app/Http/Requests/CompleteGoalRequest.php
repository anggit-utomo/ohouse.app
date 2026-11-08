<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteGoalRequest extends FormRequest
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
            'budget_id' => [
                'nullable',
                'integer',
                Rule::exists('budgets', 'id')
                    ->where('household_id', $this->household()->id)
                    ->whereNull('archived_at'),
            ],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
