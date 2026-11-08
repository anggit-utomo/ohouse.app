<?php

namespace App\Support;

use App\Enums\GoalMovementTypeEnum;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The read projection of a Goal's ledger: its movements and what was saved in
 * each Period, both computed in PHP because amounts are encrypted.
 *
 * @phpstan-import-type Row from GoalMovement
 */
final class GoalLedger
{
    /**
     * @return array{
     *     movements: list<Row>,
     *     savings: list<array{
     *         period_start: string,
     *         label: string,
     *         saved: int,
     *         withdrawn: int
     *     }>
     * }
     */
    public static function for(Goal $goal, Household $household): array
    {
        $periods = Periods::all($household)->keyBy(fn (Period $period) => $period->key());
        $current = Periods::current($household)->key();

        $movements = $goal->movements
            ->sortByDesc(fn (GoalMovement $movement) => [$movement->occurred_at->timestamp, $movement->id])
            ->values();

        $savings = $goal->movements
            ->groupBy(fn (GoalMovement $movement) => $movement->period_start->toDateString())
            ->map(fn (Collection $group, string $key) => [
                'period_start' => $key,
                'label' => $periods->get($key)?->title()
                    ?? CarbonImmutable::parse($key)->format('F'),
                'saved' => $group
                    ->filter(fn (GoalMovement $movement) => $movement->type === GoalMovementTypeEnum::CONTRIBUTION)
                    ->sum('amount'),
                'withdrawn' => $group
                    ->filter(fn (GoalMovement $movement) => $movement->type === GoalMovementTypeEnum::WITHDRAWAL)
                    ->sum('amount'),
            ])
            ->sortByDesc(fn (array $row) => $row['period_start'])
            ->values()
            ->all();

        return [
            'movements' => array_values($movements
                ->map(fn (GoalMovement $movement) => $movement->row(
                    $movement->period_start->toDateString() === $current,
                ))
                ->all()),
            'savings' => array_values($savings),
        ];
    }
}
