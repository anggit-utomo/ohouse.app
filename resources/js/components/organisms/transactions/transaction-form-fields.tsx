import { InertiaForm } from '@inertiajs/react';
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
import { currency, plain, rupiah } from '@/lib/money';
import { Defaults } from '@/types';

type Props = {
    form: InertiaForm<Defaults>;
    budgets: { id: number; name: string; left: number }[];
    members: { id: number; name: string }[];
    today: string;
    showAfterBalance?: boolean;
};

export function TransactionFormFields({
    form,
    budgets,
    members,
    today,
    showAfterBalance = false,
}: Props) {
    const isIncome = form.data.type === 'income';
    const budget = budgets.find((b) => String(b.id) === form.data.budget_id);
    const amount = Number(form.data.amount || 0);
    const after = budget ? budget.left + (isIncome ? amount : -amount) : null;
    const selectedBudget = budgets.find(
        (b) => String(b.id) === String(form.data.budget_id),
    );
    const selectedMember = members.find(
        (m) => String(m.id) === String(form.data.user_id),
    );

    return (
        <div className="space-y-4">
            {/* Type selector */}
            <div>
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
                    <p className="mt-1 text-sm text-destructive">
                        {form.errors.type}
                    </p>
                )}
            </div>

            {/* Amount Input */}
            <div className="py-2 text-center">
                <Label htmlFor="amount" className="text-muted-foreground">
                    Amount
                </Label>
                <div className="flex items-baseline justify-center gap-2">
                    <span className="text-xl text-muted-foreground">
                        {isIncome ? `+${currency().symbol}` : currency().symbol}
                    </span>
                    <input
                        id="amount"
                        inputMode="numeric"
                        placeholder="0"
                        className={`w-48 bg-transparent text-center text-4xl font-bold outline-none ${
                            isIncome ? 'text-green-600' : ''
                        }`}
                        value={
                            form.data.amount
                                ? plain(Number(form.data.amount))
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
                    <p className="mt-1 text-sm text-destructive">
                        {form.errors.amount}
                    </p>
                )}
            </div>

            {/* Budget Select */}
            <div className="grid gap-2">
                <Label>{isIncome ? 'Add to budget' : 'Budget source'}</Label>
                <Select
                    value={
                        form.data.budget_id ? String(form.data.budget_id) : ''
                    }
                    onValueChange={(v) => form.setData('budget_id', v)}
                >
                    <SelectTrigger className="w-full">
                        <SelectValue placeholder="Choose a budget">
                            {selectedBudget ? (
                                <span>
                                    {selectedBudget.name} ·{' '}
                                    {selectedBudget.left < 0
                                        ? `over by ${rupiah(-selectedBudget.left)}`
                                        : `${rupiah(selectedBudget.left)} left`}
                                </span>
                            ) : null}
                        </SelectValue>
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
                {showAfterBalance && budget && after !== null && (
                    <p
                        className={`text-sm ${
                            after < 0
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        }`}
                    >
                        After this:{' '}
                        {after < 0
                            ? `over by ${rupiah(-after)}`
                            : `${rupiah(after)} left`}{' '}
                        in {budget.name}
                    </p>
                )}
            </div>

            {/* Note Input */}
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

            {/* Date & User Select */}
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
                        value={
                            form.data.user_id ? String(form.data.user_id) : ''
                        }
                        onValueChange={(v) => form.setData('user_id', v)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Select member">
                                {selectedMember ? selectedMember.name : null}
                            </SelectValue>
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

            {isIncome && (
                <p className="text-xs text-muted-foreground">
                    Income adds to the budget you pick, so it can be spent
                    again.
                </p>
            )}
        </div>
    );
}
