<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class EntryRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param 'expense'|'income'|'move' $kind */
    public function __construct(
        public int $householdId,
        public int $actorId,
        public string $kind,
        public int $transactionId, // for a move: the money-out side
        public int $amount,
    ) {}
}
