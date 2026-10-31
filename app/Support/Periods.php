<?php

namespace App\Support;

use App\Models\BudgetMonth;
use App\Models\Household;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class Periods
{
    /** The open period: the date stored on the household, or the newest open budget rows. */
    public static function current(Household $household): Period
    {
        $start = $household->period_start
            ?? BudgetMonth::forHousehold($household)->whereNull('closed_at')->max('period_start')
            ?? now()->toDateString();

        return new Period(CarbonImmutable::parse($start)->startOfDay());
    }

    /** @return Collection<int, Period> newest first: the open period, then the closed ones */
    public static function all(Household $household): Collection
    {
        $current = self::current($household);

        $closed = BudgetMonth::forHousehold($household)
            ->whereNotNull('closed_at')
            ->whereDate('period_start', '<', $current->start->toDateString())
            ->get(['id', 'period_start', 'period_end'])
            ->unique(fn (BudgetMonth $m) => $m->period_start->toDateString())
            ->sortByDesc(fn (BudgetMonth $m) => $m->period_start->toDateString())
            ->map(fn (BudgetMonth $m) => new Period(
                CarbonImmutable::parse($m->period_start)->startOfDay(),
                CarbonImmutable::parse(
                    $m->period_end ?? $m->period_start->copy()->endOfMonth()
                )->startOfDay(),
            ));

        return collect([$current])->concat($closed)->values();
    }

    /**
     * "2026-10-27" is exact; "2026-10" is the period that started in October;
     * anything else is the open period.
     */
    public static function find(Household $household, ?string $key): Period
    {
        $all = self::all($household);

        if ($key && preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
            return $all->first(fn (Period $p) => $p->key() === $key) ?? $all->first();
        }

        if ($key && preg_match('/^\d{4}-\d{2}$/', $key)) {
            return $all->first(fn (Period $p) => $p->start->format('Y-m') === $key)
                ?? $all->first();
        }

        return $all->first();
    }

    public static function containing(Household $household, CarbonInterface $date): ?Period
    {
        return self::all($household)->first(fn (Period $p) => $p->contains($date));
    }
}
