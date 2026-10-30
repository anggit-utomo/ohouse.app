import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Row = { id: number | null; name: string; amount: string };

type Props = {
    household: { id: number; currency: string };
    period: string;
    periods: { value: string; label: string }[];
    budgets: { id: number; name: string; amount: number }[];
    repeat: boolean;
    canEdit: boolean;
};

const rupiah = (value: number) =>
    'Rp ' + new Intl.NumberFormat('id-ID').format(value);

export default function EditBudgets({
    household,
    period,
    periods,
    budgets,
    repeat,
    canEdit,
}: Props) {
    const url = `/h/${household.id}/budgets`;

    const form = useForm<{ period: string; repeat: boolean; budgets: Row[] }>({
        period,
        repeat,
        budgets: budgets.map((b) => ({
            id: b.id,
            name: b.name,
            amount: String(b.amount),
        })),
    });

    const total = form.data.budgets.reduce(
        (sum, b) => sum + Number(b.amount || 0),
        0,
    );
    const error = (i: number, field: 'name' | 'amount') =>
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
            ...d,
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
                <div className="grid gap-2">
                    <Label>Month</Label>
                    <Select
                        value={form.data.period}
                        onValueChange={(month) => router.get(url, { month })}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {periods.map((p) => (
                                <SelectItem key={p.value} value={p.value}>
                                    {p.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {form.errors.period && (
                        <p className="text-sm text-destructive">
                            {form.errors.period}
                        </p>
                    )}
                </div>

                <div className="flex items-center justify-between rounded-xl border p-4">
                    <span className="text-muted-foreground">Total budget</span>
                    <span className="text-xl font-bold">{rupiah(total)}</span>
                </div>

                <div className="grid gap-3">
                    <Label>Categories</Label>
                    {form.data.budgets.map((row, i) => (
                        <div key={row.id ?? `new-${i}`} className="grid gap-1">
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
                                    { id: null, name: '', amount: '' },
                                ])
                            }
                        >
                            <Plus className="size-4" /> Add category
                        </Button>
                    )}
                </div>

                <div className="flex items-center justify-between">
                    <div>
                        <Label htmlFor="repeat">Repeat every month</Label>
                        <p className="text-sm text-muted-foreground">
                            Reuse these amounts next month
                        </p>
                    </div>
                    <Switch
                        id="repeat"
                        checked={form.data.repeat}
                        disabled={!canEdit}
                        onCheckedChange={(v: any) => form.setData('repeat', v)}
                    />
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
