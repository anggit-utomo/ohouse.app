<?php

namespace App\Models;

use App\Casts\EncryptedInteger;
use App\Enums\GoalMovementTypeEnum;
use Database\Factories\GoalMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @phpstan-type Row array{
 *     id: int,
 *     type: string,
 *     amount: int,
 *     signed: int,
 *     note: string|null,
 *     user: string,
 *     occurred_at: string,
 *     period_start: string,
 *     can_remove: bool
 * }
 */
#[Fillable([
    'goal_id',
    'household_id',
    'user_id',
    'budget_month_id',
    'type',
    'amount',
    'note',
    'occurred_at',
    'period_start',
])]
class GoalMovement extends Model
{
    /** @use HasFactory<GoalMovementFactory> */
    use HasFactory;

    protected $casts = [
        'type' => GoalMovementTypeEnum::class,
        'amount' => EncryptedInteger::class,
        'note' => 'encrypted',
        'occurred_at' => 'datetime',
        'period_start' => 'date',
    ];

    /** @return BelongsTo<Goal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<BudgetMonth, $this> */
    public function budgetMonth(): BelongsTo
    {
        return $this->belongsTo(BudgetMonth::class);
    }

    public function signedAmount(): int
    {
        return $this->type->sign() * $this->amount;
    }

    /**
     * The read projection shown in a goal's ledger.
     *
     * @return Row
     */
    public function row(bool $canRemove = false): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'signed' => $this->signedAmount(),
            'note' => $this->note,
            'user' => $this->user->name,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'period_start' => $this->period_start->toDateString(),
            'can_remove' => $canRemove,
        ];
    }
}
