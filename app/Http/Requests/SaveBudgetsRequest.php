<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesHousehold;
use App\Models\BudgetMonth;
use App\Support\Periods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveBudgetsRequest extends FormRequest
{
    use ResolvesHousehold;

    public function authorize(): bool
    {
        return $this->user()->can('edit', $this->household());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $household = $this->household();

        return [
            'budgets' => ['present', 'array', 'max:30'],
            'budgets.*.id' => [
                'nullable',
                'integer',
                Rule::exists('budgets', 'id')->where('household_id', $household->id),
            ],
            'budgets.*.name' => ['required', 'string', 'max:50'],
            'budgets.*.amount' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'budgets.*.repeat' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $household = $this->household();
            $period = Periods::current($household);

            $closed = BudgetMonth::forHousehold($household)
                ->whereDate('period_start', $period->key())
                ->whereNotNull('closed_at')
                ->exists();

            if ($closed) {
                $validator->errors()->add('period', 'This period is already closed.');
            }
        }];
    }
}
