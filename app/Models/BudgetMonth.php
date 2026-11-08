<?php

namespace App\Models;

use App\Casts\EncryptedInteger;
use App\Enums\GoalMovementTypeEnum;
use Database\Factories\BudgetMonthFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'budget_id',
    'period_start',
    'period_end',
    'allocated',
    'carried_in',
    'carry_decision',
    'closed_at',
])]
class BudgetMonth extends Model
{
    /** @use HasFactory<BudgetMonthFactory> */
    use HasFactory;

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'allocated' => EncryptedInteger::class,
        'carried_in' => EncryptedInteger::class,
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<GoalMovement, $this> */
    public function goalMovements(): HasMany
    {
        return $this->hasMany(GoalMovement::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** Spent so far (expenses and money moved out). Amounts are encrypted, so this sums in PHP. */
    public function spent(): int
    {
        return $this->transactions
            ->filter(fn (Transaction $t) => $t->type->sign() < 0)
            ->sum('amount');
    }

    /** What is left: allocated + carried in + income/transfers in - spent - money saved. */
    public function balance(): int
    {
        $net = $this->transactions->sum(fn (Transaction $t) => $t->signedAmount());

        return $this->allocated + $this->carried_in + $net
            - $this->movedToGoals()
            + $this->returnedFromGoals();
    }

    /**
     * Numbers for one budget this month. Amounts are encrypted, so this runs in PHP.
     *
     * @return array{spent: int, left: int, available: int, moved: int, pct: int, over: bool}
     */
    public function summary(): array
    {
        $spent = $this->spent();
        $left = $this->balance();
        $moved = $this->movedToGoals();
        $available = $this->allocated + $this->carried_in + $this->incoming() + $this->returnedFromGoals();

        return [
            'spent' => $spent,
            'left' => $left,
            'available' => $available,
            'moved' => $moved,
            'pct' => $available > 0 ? min(100, (int) round($spent / $available * 100)) : ($spent > 0 ? 100 : 0),
            'over' => $left < 0,
        ];
    }

    /** Everything that came into the budget: income and incoming moves. */
    private function incoming(): int
    {
        return $this->transactions
            ->filter(fn (Transaction $t) => $t->type->sign() > 0)
            ->sum('amount');
    }

    /** Money that left this budget for a goal. */
    private function movedToGoals(): int
    {
        return $this->goalMovements
            ->filter(fn (GoalMovement $m) => $m->type === GoalMovementTypeEnum::CONTRIBUTION)
            ->sum('amount');
    }

    /** Money returned to this budget from a goal. */
    private function returnedFromGoals(): int
    {
        return $this->goalMovements
            ->filter(fn (GoalMovement $m) => $m->type === GoalMovementTypeEnum::WITHDRAWAL)
            ->sum('amount');
    }

    /**
     * @param  Builder<BudgetMonth>  $q
     * @return Builder<BudgetMonth> for the given household, via the budget relation.
     */
    public function scopeForHousehold(Builder $q, Household $household): Builder
    {
        return $q->whereHas('budget', fn ($b) => $b->where('household_id', $household->id));
    }
}
