import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { rupiah } from '@/lib/money';

type Row = { id: number | null; name: string; amount: string; repeat: boolean };

type Props = {
    household: { id: number };
    periodLabel: string;
    justStarted: boolean;
    budgets: { id: number; name: string; amount: number; repeat: boolean }[];
    canEdit: boolean;
};

export default function EditBudgets({
    household,
    periodLabel,
    justStarted,
    budgets,
    canEdit,
}: Props) {
    const url = `/h/${household.id}/budgets`;
    const form = useForm<{ budgets: Row[] }>({
        budgets: budgets.map((b) => ({
            id: b.id,
            name: b.name,
            amount: String(b.amount),
            repeat: b.repeat,
        })),
    });

    const total = form.data.budgets.reduce(
        (sum, b) => sum + Number(b.amount || 0),
        0,
    );
    const error = (i: number, field: string) =>
        (form.errors as Record<string, string>)[`budgets.${i}.${field}`];
    const setRow = (i: number, patch: Partial<Row>) =>
        form.setData(
            'budgets',
            form.data.budgets.map((row, n) =>
                n === i ? { ...row, ...patch } : row,
            ),
        );

    const save = () => {
        form.transform((d) => ({
            budgets: d.budgets.map((b) => ({
                ...b,
                amount: Number(b.amount || 0),
            })),
        }));

        form.put(url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Set budgets" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-28">
                <PageHeader title="Set budgets" href={`/h/${household.id}`} />
                <p className="-mt-2 text-sm text-muted-foreground">
                    {periodLabel}
                </p>

                {form.errors.period && (
                    <p className="rounded-lg border border-destructive/30 p-3 text-sm text-destructive">
                        {form.errors.period}
                    </p>
                )}

                {justStarted && (
                    <p className="rounded-lg border p-3 text-sm">
                        New period started. Budgets that repeat are filled in.
                        Add the ones that don't repeat below.
                    </p>
                )}

                <div className="flex items-center justify-between rounded-xl border p-4">
                    <span className="text-muted-foreground">Total budget</span>
                    <span className="text-xl font-bold">{rupiah(total)}</span>
                </div>

                <div className="grid gap-4">
                    {form.data.budgets.map((row, i) => (
                        <div
                            key={row.id ?? `new-${i}`}
                            className="grid gap-2 rounded-xl border p-3"
                        >
                            <div className="flex items-center gap-2">
                                <Input
                                    aria-label="Category name"
                                    className="w-32 shrink-0"
                                    value={row.name}
                                    placeholder="Name"
                                    disabled={!canEdit}
                                    onChange={(e) =>
                                        setRow(i, { name: e.target.value })
                                    }
                                />
                                <div className="relative flex-1">
                                    <span className="absolute top-2 left-3 text-sm text-muted-foreground">
                                        Rp
                                    </span>
                                    <Input
                                        aria-label="Amount"
                                        inputMode="numeric"
                                        className="pl-10 text-right font-semibold"
                                        value={
                                            row.amount
                                                ? new Intl.NumberFormat(
                                                      'id-ID',
                                                  ).format(Number(row.amount))
                                                : ''
                                        }
                                        disabled={!canEdit}
                                        onChange={(e) =>
                                            setRow(i, {
                                                amount: e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            })
                                        }
                                    />
                                </div>
                                {canEdit && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Remove ${row.name || 'category'}`}
                                        onClick={() =>
                                            form.setData(
                                                'budgets',
                                                form.data.budgets.filter(
                                                    (_, n) => n !== i,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                )}
                            </div>

                            <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Switch
                                    checked={row.repeat}
                                    disabled={!canEdit}
                                    onCheckedChange={(v) =>
                                        setRow(i, { repeat: v })
                                    }
                                />
                                {row.repeat
                                    ? 'Repeats every period'
                                    : 'One-off: ends with this period'}
                            </label>

                            {(error(i, 'name') || error(i, 'amount')) && (
                                <p className="text-sm text-destructive">
                                    {error(i, 'name') ?? error(i, 'amount')}
                                </p>
                            )}
                        </div>
                    ))}

                    {canEdit && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.setData('budgets', [
                                    ...form.data.budgets,
                                    {
                                        id: null,
                                        name: '',
                                        amount: '',
                                        repeat: true,
                                    },
                                ])
                            }
                        >
                            <Plus className="size-4" /> Add category
                        </Button>
                    )}
                </div>
            </div>

            {canEdit && (
                <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                    <div className="mx-auto max-w-md">
                        <Button
                            className="h-11 w-full"
                            disabled={form.processing}
                            onClick={save}
                        >
                            {form.processing ? 'Saving…' : 'Save budgets'}
                        </Button>
                    </div>
                </div>
            )}
        </>
    );
}
