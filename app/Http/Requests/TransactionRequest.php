<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction
            ? $this->user()->can('update', $transaction)
            : $this->user()->can('edit', $this->route('household'));
    }

    public function rules(): array
    {
        $id = $this->route('household')->id;

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
