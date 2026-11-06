<?php

namespace App\Listeners;

use App\Enums\TransactionTypeEnum;
use App\Events\EntryRecorded;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\EntryAdded;
use App\Support\Currencies;
use App\Support\PushPayload;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyHouseholdOfEntry implements ShouldQueue
{
    public int $tries = 3;

    public function handle(EntryRecorded $event): void
    {
        $household = Household::find($event->householdId);
        $actor = User::find($event->actorId);
        $transaction = Transaction::with('budgetMonth.budget')->find($event->transactionId);

        if (! $household || ! $actor || ! $transaction) {
            return; // deleted in the meantime
        }

        $recipients = $household->members()
            ->where('users.id', '!=', $actor->id)
            ->wherePivot('notify', true)
            ->has('pushSubscriptions')
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        [$title, $what] = $this->describe($event, $transaction, $actor);
        $money = Currencies::format($event->amount, $household->currency);
        $amount = $event->kind === 'income' ? "+{$money}" : $money;

        foreach ($recipients as $member) {
            $member->notify(new EntryAdded(new PushPayload(
                title: $title,
                body: $member->pivot->notify_amounts ? "{$what} · {$amount}" : $what,
                url: "/h/{$household->id}/transactions",
                tag: "entry-{$transaction->id}", // a retry replaces the notification, so no duplicates
            )));
        }
    }

    /** @return array{string, string} the title, and what it was about (a budget, or "From → To") */
    private function describe(EntryRecorded $event, Transaction $transaction, User $actor): array
    {
        if ($event->kind === 'transfer') {
            $pair = Transaction::where('transfer_id', $transaction->transfer_id)->with('budgetMonth.budget')->get();
            $from = $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_OUT)?->budgetMonth->budget->name;
            $to = $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_IN)?->budgetMonth->budget->name;

            return ["{$actor->name} moved money", "{$from} → {$to}"];
        }

        return [
            $event->kind === 'income' ? "{$actor->name} added income" : "{$actor->name} added an expense",
            $transaction->budgetMonth->budget->name,
        ];
    }
}
