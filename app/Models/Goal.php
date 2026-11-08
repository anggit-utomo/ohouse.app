<?php

namespace App\Models;

use App\Casts\EncryptedInteger;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * @param  Builder<Goal>  $q
     * @return Builder<Goal>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    /** Contributions arrive in a later ticket, so a Goal starts empty. */
    public function balance(): int
    {
        return 0;
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
}
