<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class Period
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly ?CarbonImmutable $end = null
    ) {}

    public function key(): string
    {
        return $this->start->toDateString();
    }

    /** The open period has no end yet. */
    public function isCurrent(): bool
    {
        return $this->end === null;
    }

    public function endOrNow(): CarbonImmutable
    {
        return ($this->end ?? CarbonImmutable::parse(now()))->endOfDay();
    }

    public function contains(CarbonInterface $date): bool
    {
        return $date >= $this->start->startOfDay() && $date <= $this->endOrNow();
    }

    /** Named by the month it starts in: 27 Oct - 26 Nov is "October". */
    public function title(): string
    {
        return $this->start->format('F');
    }

    public function range(): string
    {
        return $this->start
            ->format('j M').' – '.($this->end ? $this->end->format('j M') : 'today');
    }

    public function label(): string
    {
        return $this->title().' ('.$this->range().')';
    }
}
