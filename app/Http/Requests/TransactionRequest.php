<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesHousehold;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    use ResolvesHousehold;

    public function authorize(): bool
    {
        /** @var Transaction|null $transaction */
        $transaction = $this->route('transaction');

        return $transaction
            ? $this->user()->can('update', $transaction)
            : $this->user()->can('edit', $this->household());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->household()->id;

        return [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'budget_id' => ['required', 'integer', Rule::exists('budgets', 'id')->where('household_id', $id)],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:200'],
            'occurred_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'user_id' => ['required', 'integer', Rule::exists('household_user', 'user_id')->where('household_id', $id)],
            'corrects_transaction_id' => [
                'nullable',
                'integer',
                Rule::exists('transactions', 'id')->where('household_id', $id),
            ],
        ];
    }
}
