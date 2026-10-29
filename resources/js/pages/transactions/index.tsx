import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { rupiah } from '@/lib/money';
import { BottomNav } from '@/components/bottom-nav';

type Tx = {
    id: number;
    title: string;
    budget: string;
    user: string;
    type: string;
    kind: string;
    amount: number;
    signed: number;
    occurred_at: string;
};

type Props = {
    household: { id: number };
    month: string;
    months: { value: string; label: string }[];
    filters: { type: string | null; person: number | null; q: string };
    members: { id: number; name: string }[];
    totals: { expense: number; income: number };
    transactions: Tx[];
};

const dayLabel = (iso: string) => {
    const d = new Date(iso);
    const days = Math.round(
        (new Date().setHours(0, 0, 0, 0) - new Date(d).setHours(0, 0, 0, 0)) /
            86_400_000,
    );
    if (days === 0) return 'Today';
    if (days === 1) return 'Yesterday';
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
};

export default function History({
    household,
    month,
    months,
    filters,
    members,
    totals,
    transactions,
}: Props) {
    const url = `/h/${household.id}/transactions`;
    const [q, setQ] = useState(filters.q);

    const go = (patch: Record<string, string | number | null>) =>
        router.get(
            url,
            {
                month,
                type: filters.type,
                person: filters.person,
                q: filters.q,
                ...patch,
            },
            { preserveState: true, replace: true },
        );

    const groups = transactions.reduce<Record<string, Tx[]>>((acc, t) => {
        (acc[dayLabel(t.occurred_at)] ??= []).push(t);
        return acc;
    }, {});

    const chip = (label: string, active: boolean, onClick: () => void) => (
        <Button
            key={label}
            type="button"
            size="sm"
            variant={active ? 'default' : 'outline'}
            className="rounded-full"
            onClick={onClick}
        >
            {label}
        </Button>
    );

    return (
        <>
            <Head title="History" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-3 p-4 pb-24">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">History</h1>
                    <Button size="sm" asChild>
                        <Link href={`${url}/create`}>+ Add</Link>
                    </Button>
                </div>

                <Select value={month} onValueChange={(m) => go({ month: m })}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {months.map((m) => (
                            <SelectItem key={m.value} value={m.value}>
                                {m.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        go({ q });
                    }}
                >
                    <Input
                        placeholder="Search transactions"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                </form>

                <div className="flex flex-wrap gap-2">
                    {chip('All', !filters.type && !filters.person, () =>
                        go({ type: null, person: null }),
                    )}
                    {chip('Expense', filters.type === 'expense', () =>
                        go({
                            type: filters.type === 'expense' ? null : 'expense',
                        }),
                    )}
                    {chip('Income', filters.type === 'income', () =>
                        go({
                            type: filters.type === 'income' ? null : 'income',
                        }),
                    )}
                    {members.map((m) =>
                        chip(m.name, filters.person === m.id, () =>
                            go({
                                person: filters.person === m.id ? null : m.id,
                            }),
                        ),
                    )}
                </div>

                <div className="flex justify-between text-sm text-muted-foreground">
                    <span>Spent {rupiah(totals.expense)}</span>
                    <span>Income {rupiah(totals.income)}</span>
                </div>

                {transactions.length === 0 && (
                    <p className="py-10 text-center text-muted-foreground">
                        No transactions found.
                    </p>
                )}

                {Object.entries(groups).map(([day, rows]) => (
                    <div key={day}>
                        <p className="mt-2 mb-1 text-sm font-medium text-muted-foreground">
                            {day}
                        </p>
                        {rows.map((t) => (
                            <Link
                                key={t.id}
                                href={`${url}/${t.id}`}
                                className="flex items-center gap-3 py-2.5"
                            >
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                    {t.user.charAt(0).toUpperCase()}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-semibold">
                                        {t.title}
                                    </span>
                                    <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Badge variant="outline">
                                            {t.budget}
                                        </Badge>
                                        {new Date(
                                            t.occurred_at,
                                        ).toLocaleTimeString('en-GB', {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })}
                                    </span>
                                </span>
                                <span
                                    className={`font-semibold ${t.signed > 0 ? 'text-green-700' : ''}`}
                                >
                                    {t.kind === 'transfer'
                                        ? rupiah(t.amount)
                                        : `${t.signed > 0 ? '+' : '-'}${rupiah(Math.abs(t.signed))}`}
                                </span>
                            </Link>
                        ))}
                    </div>
                ))}
            </div>

            <BottomNav householdId={household.id} active="history" />
        </>
    );
}
