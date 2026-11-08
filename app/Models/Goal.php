<?php

namespace App\Models;

use App\Casts\EncryptedInteger;
use App\Enums\GoalMovementTypeEnum;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['household_id', 'name', 'target_amount', 'target_date', 'sort', 'archived_at'])]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    protected $casts = [
        'name' => 'encrypted',
        'target_amount' => EncryptedInteger::class,
        'target_date' => 'date',
        'archived_at' => 'datetime',
    ];

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return HasMany<GoalMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(GoalMovement::class);
    }

    /**
     * @param  Builder<Goal>  $q
     * @return Builder<Goal>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    /**
     * @param  Builder<Goal>  $q
     * @return Builder<Goal>
     */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort')->orderBy('id');
    }

    /** A Goal's balance is its contributions minus its withdrawals, summed in PHP. */
    public function balance(): int
    {
        return $this->movements->sum(fn (GoalMovement $movement) => $movement->signedAmount());
    }

    /**
     * Numbers for one goal. Amounts are encrypted, so this runs in PHP.
     *
     * @return array{balance: int, target: int, pct: int, remaining: int, over: bool}
     */
    public function summary(): array
    {
        $balance = $this->balance();
        $target = $this->target_amount;

        return [
            'balance' => $balance,
            'target' => $target,
            'pct' => $target > 0 ? (int) round($balance / $target * 100) : 0,
            'remaining' => max(0, $target - $balance),
            'over' => $balance > $target,
        ];
    }

    /**
     * The read projection shown in a list or detail view.
     *
     * @return array{id: int, name: string, sort: int, balance: int, target: int, pct: int, remaining: int, over: bool}
     */
    public function row(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort' => $this->sort,
            ...$this->summary(),
        ];
    }

    /**
     * The history projection: what the Goal finished with, not what is left.
     *
     * @return array{id: int, name: string, saved: int, target: int, archived_at: string|null}
     */
    public function archivedRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'saved' => $this->movements
                ->filter(fn (GoalMovement $movement) => $movement->type === GoalMovementTypeEnum::CONTRIBUTION)
                ->sum('amount'),
            'target' => $this->target_amount,
            'archived_at' => $this->archived_at?->toDateString(),
        ];
    }
}
