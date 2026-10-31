import { Head, Link, useForm } from '@inertiajs/react';
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
import { PageHeader } from '@/components/page-header';

type Defaults = {
    type: 'expense' | 'income';
    budget_id: string;
    amount: string;
    note: string;
    occurred_at: string;
    user_id: string;
    corrects_transaction_id: number | null;
};

type Props = {
    household: { id: number };
    mode: 'create' | 'edit';
    transactionId?: number;
    budgets: { id: number; name: string; left: number }[];
    members: { id: number; name: string }[];
    defaults: Defaults;
    today: string;
};

export default function TransactionForm({
    household,
    mode,
    transactionId,
    budgets,
    members,
    defaults,
    today,
}: Props) {
    const base = `/h/${household.id}/transactions`;
    const form = useForm<Defaults>(defaults);
    const isIncome = form.data.type === 'income';
    const budget = budgets.find((b) => String(b.id) === form.data.budget_id);
    const amount = Number(form.data.amount || 0);
    const after = budget ? budget.left + (isIncome ? amount : -amount) : null;

    const submit = () => {
        form.transform((d) => ({
            ...d,
            amount: Number(d.amount || 0),
            budget_id: Number(d.budget_id),
            user_id: Number(d.user_id),
        }));
        if (mode === 'edit') form.put(`${base}/${transactionId}`);
        else form.post(base);
    };

    return (
        <>
            <Head
                title={mode === 'edit' ? 'Edit transaction' : 'New transaction'}
            />

            <PageHeader
                title={mode === 'edit' ? 'Edit transaction' : 'New transaction'}
                href={mode === 'edit' ? `${base}/${transactionId}` : base}
            />

            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-28">
                <div className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1">
                    {(['expense', 'income'] as const).map((t) => (
                        <Button
                            key={t}
                            type="button"
                            variant={form.data.type === t ? 'default' : 'ghost'}
                            onClick={() => form.setData('type', t)}
                        >
                            {t === 'expense' ? 'Expense' : 'Income'}
                        </Button>
                    ))}
                </div>
                {form.errors.type && (
                    <p className="text-sm text-destructive">
                        {form.errors.type}
                    </p>
                )}

                <div className="py-4 text-center">
                    <Label htmlFor="amount" className="text-muted-foreground">
                        Amount
                    </Label>
                    <div className="flex items-baseline justify-center gap-2">
                        <span className="text-xl text-muted-foreground">
                            {isIncome ? '+Rp' : 'Rp'}
                        </span>
                        <input
                            id="amount"
                            inputMode="numeric"
                            placeholder="0"
                            className={`w-48 bg-transparent text-center text-4xl font-bold outline-none ${isIncome ? 'text-green-700' : ''}`}
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
                    {form.errors.amount && (
                        <p className="text-sm text-destructive">
                            {form.errors.amount}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label>
                        {isIncome ? 'Add to budget' : 'Budget source'}
                    </Label>
                    <Select
                        value={form.data.budget_id}
                        onValueChange={(v) => form.setData('budget_id', v)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Choose a budget" />
                        </SelectTrigger>
                        <SelectContent>
                            {budgets.map((b) => (
                                <SelectItem key={b.id} value={String(b.id)}>
                                    {b.name} ·{' '}
                                    {b.left < 0
                                        ? `over by ${rupiah(-b.left)}`
                                        : `${rupiah(b.left)} left`}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {form.errors.budget_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.budget_id}
                        </p>
                    )}
                    {mode === 'create' && budget && after !== null && (
                        <p
                            className={`text-sm ${after < 0 ? 'text-destructive' : 'text-muted-foreground'}`}
                        >
                            After this:{' '}
                            {after < 0
                                ? `over by ${rupiah(-after)}`
                                : `${rupiah(after)} left`}{' '}
                            in {budget.name}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="note">Note</Label>
                    <Input
                        id="note"
                        placeholder="Groceries at the market"
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                    />
                    {form.errors.note && (
                        <p className="text-sm text-destructive">
                            {form.errors.note}
                        </p>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="date">Date</Label>
                        <Input
                            id="date"
                            type="date"
                            max={today}
                            value={form.data.occurred_at}
                            onChange={(e) =>
                                form.setData('occurred_at', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>{isIncome ? 'Received by' : 'Paid by'}</Label>
                        <Select
                            value={form.data.user_id}
                            onValueChange={(v) => form.setData('user_id', v)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {members.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                {(form.errors.occurred_at || form.errors.user_id) && (
                    <p className="text-sm text-destructive">
                        {form.errors.occurred_at ?? form.errors.user_id}
                    </p>
                )}

                <p className="text-sm text-muted-foreground">
                    Income adds to the budget you pick, so it can be spent
                    again.
                </p>
            </div>

            <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                <div className="mx-auto flex max-w-md gap-2">
                    {mode === 'edit' && (
                        <Button variant="outline" className="h-11" asChild>
                            <Link href={`${base}/${transactionId}`}>
                                Cancel
                            </Link>
                        </Button>
                    )}
                    <Button
                        className="h-11 flex-1"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        {form.processing
                            ? 'Saving…'
                            : mode === 'edit'
                              ? 'Save changes'
                              : isIncome
                                ? 'Save income'
                                : 'Save expense'}
                    </Button>
                </div>
            </div>
        </>
    );
}
