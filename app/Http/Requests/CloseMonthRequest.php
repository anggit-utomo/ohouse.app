<?php

namespace App\Http\Requests;

use App\Actions\CloseMonth;
use App\Http\Requests\Concerns\ResolvesHousehold;
use App\Support\Periods;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CloseMonthRequest extends FormRequest
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
            'start' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'decisions' => ['present', 'array'],
            'decisions.*' => ['in:carry,reset'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $household = $this->route('household');
            $current = Periods::current($household);

            if (Carbon::parse($this->input('start'))->lte($current->start)) {
                $validator->errors()
                    ->add(
                        'start',
                        'The new period must start after '.$current->start->format('j M').'.'
                    );

                return;
            }

            $missing = app(CloseMonth::class)->open($household)
                ->filter(fn ($m) => $m->budget->repeat_monthly)
                ->pluck('budget_id')
                ->diff(array_keys($this->input('decisions')));

            if ($missing->isNotEmpty()) {
                $validator->errors()
                    ->add('decisions', 'Choose carry over or reset for every budget.');
            }
        }];
    }
}
