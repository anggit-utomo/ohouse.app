<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesHousehold;
use App\Models\BudgetMonth;
use Carbon\Carbon;
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
            'period' => ['required', 'date_format:Y-m'],
            'repeat' => ['required', 'boolean'],
            'budgets' => ['present', 'array', 'max:30'],
            'budgets.*.id' => ['nullable', 'integer', Rule::exists('budgets', 'id')->where('household_id', $household->id)],
            'budgets.*.name' => ['required', 'string', 'max:50'],
            'budgets.*.amount' => ['required', 'integer', 'min:0', 'max:100000000000'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('period')) {
                return;
            }

            $period = $this->period();

            if ($period->lt(now()->startOfMonth())) {
                $validator->errors()->add('period', 'Past months are locked. Pick this month or a later one.');

                return;
            }

            $closed = BudgetMonth::whereHas('budget', fn ($q) => $q->where('household_id', $this->household()->id))
                ->whereDate('period_start', $period->toDateString())
                ->whereNotNull('closed_at')
                ->exists();

            if ($closed) {
                $validator->errors()->add('period', 'This month is already closed.');
            }
        }];
    }

    public function period(): Carbon
    {
        return Carbon::parse($this->input('period').'-01');
    }
}
