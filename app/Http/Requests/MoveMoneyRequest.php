<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveMoneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit', $this->route('household'));
    }

    public function rules(): array
    {
        $own = Rule::exists('budgets', 'id')->where('household_id', $this->route('household')->id);

        return [
            'from_budget_id' => ['required', 'integer', $own],
            'to_budget_id' => ['required', 'integer', $own, 'different:from_budget_id'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
