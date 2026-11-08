<?php

namespace App\Enums;

enum GoalMovementTypeEnum: string
{
    case CONTRIBUTION = 'contribution';
    case WITHDRAWAL = 'withdrawal';

    public function sign(): int
    {
        return match ($this) {
            self::CONTRIBUTION => 1,
            self::WITHDRAWAL => -1,
        };
    }
}
