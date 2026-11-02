import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowUpDown, ChevronLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { rupiah } from '@/lib/money';

type Budget = { id: number; name: string; left: number };
type Form = {
    from_budget_id: string;
    to_budget_id: string;
    amount: string;
    note: string;
};
type Props = { household: { id: number }; budgets: Budget[]; defaults: Form };

const leftText = (n: number) =>
    n < 0 ? `over by ${rupiah(-n)}` : `${rupiah(n)} left`;

export default function MoveMoney({ household, budgets, defaults }: Props) {
    const base = `/h/${household.id}`;
    const form = useForm<Form>(defaults);
    const find = (id: string) => budgets.find((b) => String(b.id) === id);
    const from = find(form.data.from_budget_id);
    const to = find(form.data.to_budget_id);
    const amount = Number(form.data.amount || 0);
    const tooMuch = !!from && amount > Math.max(from.left, 0);
    const ready = !!from && !!to && amount > 0 && !tooMuch;
    const errors = form.errors as Record<string, string>;
    const error =
        errors.amount ??
        errors.from_budget_id ??
        errors.to_budget_id ??
        errors.occurred_at;

    const picker = (
        label: string,
        key: 'from_budget_id' | 'to_budget_id',
        other: string,
    ) => (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select
                value={form.data[key]}
                onValueChange={(v) => form.setData(key, v)}
            >
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Choose a budget" />
                </SelectTrigger>
                <SelectContent>
                    {budgets
                        .filter((b) => String(b.id) !== other)
                        .map((b) => (
                            <SelectItem key={b.id} value={String(b.id)}>
                                {b.name} · {leftText(b.left)}
                            </SelectItem>
                        ))}
                </SelectContent>
            </Select>
        </div>
    );

    return (
        <>
            <Head title="Move money" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-28">
                <Link
                    href={base}
                    className="flex items-center gap-1 text-lg font-semibold"
                >
                    <ChevronLeft className="size-5" />
                    Move money
                </Link>
                <p className="text-muted-foreground">
                    Cover one budget with what is left in another.
                </p>

                {picker('From', 'from_budget_id', form.data.to_budget_id)}

                <div className="flex justify-center">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Swap budgets"
                        onClick={() =>
                            form.setData((d) => ({
                                ...d,
                                from_budget_id: d.to_budget_id,
                                to_budget_id: d.from_budget_id,
                            }))
                        }
                    >
                        <ArrowUpDown className="size-5" />
                    </Button>
                </div>

                {picker('To', 'to_budget_id', form.data.from_budget_id)}

                <div className="py-4 text-center">
                    <Label htmlFor="amount" className="text-muted-foreground">
                        Amount
                    </Label>
                    <div className="flex items-baseline justify-center gap-2">
                        <span className="text-xl text-muted-foreground">
                            Rp
                        </span>
                        <input
                            id="amount"
                            inputMode="numeric"
                            placeholder="0"
                            className="w-48 bg-transparent text-center text-4xl font-bold outline-none"
                            value={
                                form.data.amount
                                    ? new Intl.NumberFormat('id-ID').format(
                                        Number(form.data.amount),
                                    )
                                    : ''
                            }
                            onChange={(e) =>
                                form.setData(
                                    'amount',
                                    e.target.value.replace(/\D/g, ''),
                                )
                            }
                        />
                    </div>
                </div>

                {from && to && amount > 0 && (
                    <div className="grid gap-2 rounded-xl border p-3 text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                {from.name} after
                            </span>
                            <b className={tooMuch ? 'text-destructive' : ''}>
                                {leftText(from.left - amount)}
                            </b>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                {to.name} after
                            </span>
                            <b
                                className={
                                    to.left + amount < 0
                                        ? 'text-destructive'
                                        : ''
                                }
                            >
                                {leftText(to.left + amount)}
                            </b>
                        </div>
                    </div>
                )}

                {(tooMuch || error) && (
                    <p className="text-sm text-destructive">
                        {error ??
                            `${from?.name} only has ${rupiah(Math.max(from?.left ?? 0, 0))} left.`}
                    </p>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="note">Note</Label>
                    <Input
                        id="note"
                        placeholder="Optional"
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                    />
                </div>
            </div>

            <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                <div className="mx-auto max-w-md">
                    <Button
                        className="h-11 w-full"
                        disabled={!ready || form.processing}
                        onClick={() => {
                            form.transform((d) => ({
                                ...d,
                                amount: Number(d.amount),
                                from_budget_id: Number(d.from_budget_id),
                                to_budget_id: Number(d.to_budget_id),
                            }));

                            form.post(`${base}/transfers`);
                        }}
                    >
                        {amount > 0 ? `Move ${rupiah(amount)}` : 'Move money'}
                    </Button>
                </div>
            </div>
        </>
    );
}
