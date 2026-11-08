<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesHousehold;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
