<?php

namespace App\Models;

use App\Support\Period;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property Carbon|null $period_start
 */
#[Fillable(['name', 'currency', 'month_start_day', 'owner_id', 'period_start'])]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    protected $casts = ['name' => 'encrypted', 'period_start' => 'date'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this, Membership, 'pivot'> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot('role', 'joined_at', 'notify', 'notify_amounts');
    }

    /** @return HasMany<Budget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Goal, $this> */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /** @return HasMany<GoalMovement, $this> */
    public function goalMovements(): HasMany
    {
        return $this->hasMany(GoalMovement::class);
    }

    /** True once any Transaction or Goal movement exists, so the currency is fixed. */
    public function hasRecordedMoney(): bool
    {
        return $this->transactions()->withTrashed()->exists()
            || $this->goalMovements()->exists();
    }

    /** @return HasMany<HouseholdInvite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(HouseholdInvite::class);
    }

    /**
     * Active goals with their movements loaded, in display order.
     *
     * @return Collection<int, Goal>
     */
    public function activeGoals(): Collection
    {
        return $this->goals()->active()->ordered()->with('movements')->get();
    }

    /**
     * Active repeating budgets that may become a Sweep source, each with the
     * goal that already claimed it. One query for every goal's picker.
     *
     * @return Collection<int, array{id: int, name: string, goal_id: int|null}>
     */
    public function sweepBudgetCandidates(): Collection
    {
        return $this->budgets()->active()
            ->where('repeat_monthly', true)
            ->with('sweepRule:id,budget_id,goal_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (Budget $budget) => [
                'id' => $budget->id,
                'name' => $budget->name,
                'goal_id' => $budget->sweepRule?->goal_id,
            ])
            ->values();
    }

    /**
     * A goal's Sweep picker options, excluding budgets claimed by another goal
     * while keeping its own. Uses the collection from sweepBudgetCandidates().
     *
     * @param  Collection<int, array{id: int, name: string, goal_id: int|null}>  $candidates
     * @return list<array{id: int, name: string}>
     */
    public function sweepBudgetOptionsFor(Goal $goal, Collection $candidates): array
    {
        return array_values($candidates
            ->filter(fn (array $budget) => $budget['goal_id'] === null || $budget['goal_id'] === $goal->id)
            ->map(fn (array $budget) => ['id' => $budget['id'], 'name' => $budget['name']])
            ->all());
    }

    /**
     * Sweep budgets nobody claimed, for creating a new goal.
     *
     * @param  Collection<int, array{id: int, name: string, goal_id: int|null}>  $candidates
     * @return list<array{id: int, name: string}>
     */
    public function unclaimedSweepBudgetOptions(Collection $candidates): array
    {
        return array_values($candidates
            ->filter(fn (array $budget) => $budget['goal_id'] === null)
            ->map(fn (array $budget) => ['id' => $budget['id'], 'name' => $budget['name']])
            ->all());
    }

    /**
     * Active repeating budgets that may still become a Goal's Sweep source,
     * excluding the ones another Goal already claimed.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function sweepBudgetOptions(?Goal $goal = null): Collection
    {
        $candidates = $this->sweepBudgetCandidates();

        return collect(
            $goal instanceof Goal
                ? $this->sweepBudgetOptionsFor($goal, $candidates)
                : $this->unclaimedSweepBudgetOptions($candidates)
        );
    }

    /**
     * Active budgets with what is left in the given period, for pickers.
     *
     * @return Collection<int, array{id: int, name: string, left: int}>
     */
    public function budgetBalances(Period $period): Collection
    {
        return $this->budgets()->active()->orderBy('sort')
            ->with([
                'months' => fn ($q) => $q->withMoney()
                    ->whereDate('period_start', $period->key()),
            ])
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'left' => $b->months->first()?->balance()
                    ?? ($b->repeat_monthly ? ($b->default_amount ?? 0) : 0),
            ])->values();
    }
}
