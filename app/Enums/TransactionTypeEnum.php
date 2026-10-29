<?php

namespace App\Enums;

enum TransactionTypeEnum: string
{
    // expense | income | transfer_in | transfer_out
    case EXPENSE = 'expense';
    case INCOME = 'income';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';

    public function sign(): int
    {
        return match ($this) {
            self::INCOME, self::TRANSFER_IN => 1,
            default => -1,
        };
    }
}
