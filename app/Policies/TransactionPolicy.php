<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return $user->roleIn($transaction->household) !== null;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->roleIn($transaction->household)?->canEdit() ?? false;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
