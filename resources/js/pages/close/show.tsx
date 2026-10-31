import { Head, router, useForm } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rupiah } from '@/lib/money';

type Choice = 'carry' | 'reset';
type Row = {
    budget_id: number;
    name: string;
    leftover: number;
    next_allocated: number;
};

type Props = {
    household: { id: number };
    currentTitle: string;
    currentRange: string;
    startDate: string;
    minDate: string;
    maxDate: string;
    rows: Row[];
    ending: string[];
    canStart: boolean;
};

export default function StartPeriod({
    household,
    currentTitle,
    currentRange,
    startDate,
    minDate,
    maxDate,
    rows,
    ending,
    canStart,
}: Props) {
    const base = `/h/${household.id}`;
    const form = useForm<{ start: string; decisions: Record<number, Choice> }>({
        start: startDate,
        decisions: Object.fromEntries(
            rows.map((r) => [r.budget_id, 'carry' as Choice]),
        ),
    });
    const errors = form.errors as Record<string, string>;
    const error = errors.start ?? errors.decisions;

    const changeDate = (value: string) => {
        form.setData('start', value);

        if (value >= minDate && value <= maxDate) {
            router.get(
                `${base}/close`,
                { date: value },
                {
                    only: ['rows', 'ending', 'canStart'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }
    };

    const set = (id: number, choice: Choice) =>
        form.setData('decisions', { ...form.data.decisions, [id]: choice });
    const total = rows.reduce(
        (sum, r) =>
            sum +
            r.next_allocated +
            (form.data.decisions[r.budget_id] === 'reset' ? 0 : r.leftover),
        0,
    );

    return (
        <>
            <Head title="Start new period" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-3 p-4 pb-32">
                <PageHeader title="Start new period" href={base} />

                <p className="text-muted-foreground">
                    Paid? Choose the day your new period starts. {currentTitle}{' '}
                    ({currentRange}) ends the day before.
                </p>

                <div className="grid gap-2">
                    <Label htmlFor="start">New period starts on</Label>
                    <Input
                        id="start"
                        type="date"
                        min={minDate}
                        max={maxDate}
                        value={form.data.start}
                        onChange={(e) => changeDate(e.target.value)}
                    />
                    <p className="text-xs text-muted-foreground">
                        Entries dated on or after this day move to the new
                        period.
                    </p>
                </div>

                {rows.length === 0 && (
                    <p className="rounded-lg border p-3 text-sm text-muted-foreground">
                        No repeating budgets. You'll add the new ones next.
                    </p>
                )}

                {rows.map((r) => (
                    <div key={r.budget_id} className="rounded-xl border p-3">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="font-semibold">{r.name}</span>
                            <span
                                className={`font-semibold ${r.leftover < 0 ? 'text-destructive' : ''}`}
                            >
                                {r.leftover === 0
                                    ? rupiah(0)
                                    : `${r.leftover > 0 ? '+' : '-'}${rupiah(Math.abs(r.leftover))}`}
                            </span>
                        </div>
                        <div className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1">
                            {(['carry', 'reset'] as const).map((c) => (
                                <Button
                                    key={c}
                                    type="button"
                                    size="sm"
                                    variant={
                                        form.data.decisions[r.budget_id] === c
                                            ? 'default'
                                            : 'ghost'
                                    }
                                    onClick={() => set(r.budget_id, c)}
                                >
                                    {c === 'carry' ? 'Carry over' : 'Reset'}
                                </Button>
                            ))}
                        </div>
                    </div>
                ))}

                {ending.length > 0 && (
                    <p className="rounded-lg border p-3 text-sm text-muted-foreground">
                        One-off budgets end with this period:{' '}
                        {ending.join(', ')}. You can create new ones next.
                    </p>
                )}

                <div className="mt-1 flex items-center justify-between">
                    <span className="text-muted-foreground">
                        New period starts with
                    </span>
                    <b className="text-lg">{rupiah(total)}</b>
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}
            </div>

            <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                <div className="mx-auto max-w-md">
                    <Button
                        className="h-11 w-full"
                        disabled={!canStart || form.processing}
                        onClick={() => form.post(`${base}/close`)}
                    >
                        {canStart
                            ? 'Start new period'
                            : 'Pick a day after the current start'}
                    </Button>
                </div>
            </div>
        </>
    );
}
