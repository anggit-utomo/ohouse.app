<?php

namespace App\Http\Requests;

use App\Actions\CloseMonth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CloseMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'date_format:Y-m'],
            'decisions' => ['required', 'array'],
            'decisions.*' => ['in:carry,reset'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $household = $this->route('household');
            $close = app(CloseMonth::class);
            $pending = $close->pending($household);

            if (! $pending || $pending->format('Y-m') !== $this->input('period')) {
                $validator->errors()->add('period', 'There is nothing to close for that month.');

                return;
            }

            if (! $close->canClose($pending)) {
                $validator->errors()->add('period', $pending->format('F').' can be closed from 1 '.$pending->copy()->addMonthNoOverflow()->format('F').'.');

                return;
            }

            $missing = $close->open($household, $pending)->pluck('budget_id')->diff(array_keys($this->input('decisions')));

            if ($missing->isNotEmpty()) {
                $validator->errors()->add('decisions', 'Choose carry over or reset for every budget.');
            }
        }];
    }
}
