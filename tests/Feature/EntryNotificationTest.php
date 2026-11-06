<?php

use App\Events\EntryRecorded;
use App\Listeners\NotifyHouseholdOfEntry;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\EntryAdded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function notificationBudget(Household $household, string $name, int $amount): BudgetMonth
{
    $budget = Budget::factory()->for($household)->create([
        'name' => $name,
        'default_amount' => $amount,
    ]);

    return BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-10-01',
        'allocated' => $amount,
        'carried_in' => 0,
    ]);
}

function notificationMember(Household $household, bool $notify = true): User
{
    $user = User::factory()->create();

    $household->members()->attach($user->id, [
        'role' => 'editor',
        'notify' => $notify,
        'notify_amounts' => false,
    ]);

    $user->updatePushSubscription(
        "https://push.example.test/{$user->id}",
        'public-key',
        'auth-token',
        'aes128gcm',
    );

    return $user;
}

function notificationTransfer(Household $household, BudgetMonth $from, BudgetMonth $to): Transaction
{
    $shared = [
        'household_id' => $household->id,
        'user_id' => $household->owner_id,
        'amount' => 100_000,
        'note' => null,
        'occurred_at' => '2026-10-15 09:00:00',
        'transfer_id' => (string) Str::uuid(),
    ];

    $from->transactions()->create([...$shared, 'type' => 'transfer_out']);

    return $to->transactions()->create([...$shared, 'type' => 'transfer_in']);
}

it('describes a transfer entry as a move', function () {
    $household = Household::factory()->create();
    $actor = $household->owner;
    $recipient = notificationMember($household);

    $gas = notificationBudget($household, 'Gasoline', 500_000);
    $transport = notificationBudget($household, 'Transport', 500_000);
    $move = notificationTransfer($household, $gas, $transport);

    Notification::fake();

    (new NotifyHouseholdOfEntry)->handle(new EntryRecorded(
        $household->id,
        $actor->id,
        'transfer',
        $move->id,
        100_000,
    ));

    Notification::assertSentTo(
        $recipient,
        EntryAdded::class,
        fn (EntryAdded $notification) => $notification->payload->title === "{$actor->name} moved money"
            && $notification->payload->body === 'Gasoline → Transport',
    );
});

it('keeps the expense notification copy', function () {
    $household = Household::factory()->create();
    $actor = $household->owner;
    $recipient = notificationMember($household);

    $groceries = notificationBudget($household, 'Groceries', 500_000);
    $expense = $groceries->transactions()->create([
        'household_id' => $household->id,
        'user_id' => $actor->id,
        'type' => 'expense',
        'amount' => 50_000,
        'occurred_at' => '2026-10-15 09:00:00',
    ]);

    Notification::fake();

    (new NotifyHouseholdOfEntry)->handle(new EntryRecorded(
        $household->id,
        $actor->id,
        'expense',
        $expense->id,
        50_000,
    ));

    Notification::assertSentTo(
        $recipient,
        EntryAdded::class,
        fn (EntryAdded $notification) => $notification->payload->title === "{$actor->name} added an expense"
            && $notification->payload->body === 'Groceries',
    );
});

it('does not notify the actor or a member with notifications off', function () {
    $household = Household::factory()->create();
    $actor = $household->owner;
    $actor->updatePushSubscription('https://push.example.test/actor', 'key', 'token', 'aes128gcm');
    $muted = notificationMember($household, notify: false);

    $gas = notificationBudget($household, 'Gasoline', 500_000);
    $transport = notificationBudget($household, 'Transport', 500_000);
    $move = notificationTransfer($household, $gas, $transport);

    Notification::fake();

    (new NotifyHouseholdOfEntry)->handle(new EntryRecorded(
        $household->id,
        $actor->id,
        'transfer',
        $move->id,
        100_000,
    ));

    Notification::assertNotSentTo($actor, EntryAdded::class);
    Notification::assertNotSentTo($muted, EntryAdded::class);
});

it('dispatches a transfer event when money moves', function () {
    $household = Household::factory()->create();
    $gas = notificationBudget($household, 'Gasoline', 500_000);
    $transport = notificationBudget($household, 'Transport', 500_000);

    Event::fake([EntryRecorded::class]);

    $this->actingAs($household->owner)
        ->post("/h/{$household->id}/transfers", [
            'from_budget_id' => $gas->budget_id,
            'to_budget_id' => $transport->budget_id,
            'amount' => 100_000,
        ])
        ->assertRedirect();

    Event::assertDispatched(
        EntryRecorded::class,
        fn (EntryRecorded $event) => $event->kind === 'transfer',
    );
});
