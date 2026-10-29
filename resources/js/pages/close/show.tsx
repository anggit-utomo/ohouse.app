import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
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
    period: string;
    periodLabel: string;
    nextLabel: string;
    closesOn: string;
    canClose: boolean;
    rows: Row[];
};

export default function CloseMonth({
    household,
    period,
    periodLabel,
    nextLabel,
    closesOn,
    canClose,
    rows,
}: Props) {
    const base = `/h/${household.id}`;
    const form = useForm<{ period: string; decisions: Record<number, Choice> }>(
        {
            period,
            decisions: Object.fromEntries(
                rows.map((r) => [r.budget_id, 'carry' as Choice]),
            ),
        },
    );

    const nextTotal = rows.reduce(
        (sum, r) =>
            sum +
            r.next_allocated +
            (form.data.decisions[r.budget_id] === 'carry' ? r.leftover : 0),
        0,
    );
    const set = (id: number, choice: Choice) =>
        form.setData('decisions', { ...form.data.decisions, [id]: choice });
    const error =
        (form.errors as Record<string, string>).period ??
        (form.errors as Record<string, string>).decisions;

    return (
        <>
            <Head title={`Close ${periodLabel}`} />

            <div className="mx-auto flex w-full max-w-md flex-col gap-3 p-4 pb-32">
                <Link
                    href={base}
                    className="flex items-center gap-1 text-lg font-semibold"
                >
                    <ChevronLeft className="size-5" />
                    Close {periodLabel}
                </Link>

                <p className="text-muted-foreground">
                    Carry over adds what is left to {nextLabel}. Reset starts
                    fresh at the set amount.
                </p>
                {!canClose && (
                    <p className="rounded-lg border p-3 text-sm text-muted-foreground">
                        {periodLabel} is not over yet. You can look at the
                        numbers now, but closing opens on {closesOn}, and your
                        choices are not saved until then.
                    </p>
                )}

                {rows.map((r) => {
                    const choice = form.data.decisions[r.budget_id];

                    return (
                        <div
                            key={r.budget_id}
                            className="rounded-xl border p-3"
                        >
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
                                            choice === c ? 'default' : 'ghost'
                                        }
                                        onClick={() => set(r.budget_id, c)}
                                    >
                                        {c === 'carry' ? 'Carry over' : 'Reset'}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    );
                })}

                <div className="mt-2 flex items-center justify-between">
                    <span className="text-muted-foreground">
                        {nextLabel} starts with
                    </span>
                    <b className="text-lg">{rupiah(nextTotal)}</b>
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}
            </div>

            <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-6">
                <div className="mx-auto max-w-md">
                    <Button
                        className="h-11 w-full"
                        disabled={
                            !canClose || form.processing || rows.length === 0
                        }
                        onClick={() => form.post(`${base}/close`)}
                    >
                        {canClose
                            ? `Close ${periodLabel} & start ${nextLabel}`
                            : `Closing opens on ${closesOn}`}
                    </Button>
                </div>
            </div>
        </>
    );
}
