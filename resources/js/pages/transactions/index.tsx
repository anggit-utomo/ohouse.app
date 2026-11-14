import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
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
import { toTransactionDetail } from '@/lib/transactions';
import { BottomNav } from '@/components/bottom-nav';
import ClosedPeriodNotice from '@/components/closed-period-notice';
import HeadTitle from '@/components/head-title';
import { TransactionDrawer } from '@/components/organisms/transactions/transaction-drawer';
import { TransactionDetailDrawer } from '@/components/organisms/transactions/transaction-detail-drawer';
import TransactionListItem from '@/components/organisms/transactions/transaction-list-item';
import type {
    BudgetOption,
    HouseholdRef,
    MemberOption,
    MonthOption,
    TransactionDefaults,
    TransactionRow,
} from '@/types';

type Props = {
    household: HouseholdRef;
    month: string;
    months: MonthOption[];
    filters: { type: string | null; person: number | null; q: string };
    members: MemberOption[];
    totals: { expense: number; income: number };
    transactions: TransactionRow[];
    canEdit: boolean;
    closed: boolean;
    budgets: BudgetOption[];
    defaults: TransactionDefaults;
    today: string;
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
    canEdit,
    closed,
    budgets,
    defaults,
    today,
}: Props) {
    const url = `/h/${household.id}/transactions`;
    const [q, setQ] = useState(filters.q);
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [selectedTransactionId, setSelectedTransactionId] = useState<
        number | null
    >(null);
    const [isDetailDrawerOpen, setIsDetailDrawerOpen] = useState(false);

    const selectedRow =
        transactions.find((row) => row.id === selectedTransactionId) ?? null;
    const selectedTransaction = selectedRow
        ? toTransactionDetail(selectedRow)
        : null;

    const handleOpenDetail = (row: TransactionRow) => {
        setSelectedTransactionId(row.id);
        setIsDetailDrawerOpen(true);
    };

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

    const groups = transactions.reduce<Record<string, TransactionRow[]>>(
        (acc, t) => {
            (acc[dayLabel(t.occurred_at)] ??= []).push(t);
            return acc;
        },
        {},
    );

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

            <HeadTitle
                left={<h1 className="text-xl font-semibold">History</h1>}
                right={
                    canEdit && (
                        <Button
                            size="sm"
                            type="button"
                            onClick={() => setIsDrawerOpen(true)}
                        >
                            + Add
                        </Button>
                    )
                }
            />

            <Select value={month} onValueChange={(m) => go({ month: m })}>
                <SelectTrigger>
                    <SelectValue>
                        {months.find((m) => m.value === month)?.label}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    {months.map((m) => (
                        <SelectItem key={m.value} value={m.value}>
                            {m.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {closed && <ClosedPeriodNotice />}

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
                        <TransactionListItem
                            key={t.id}
                            row={t}
                            onSelect={handleOpenDetail}
                        />
                    ))}
                </div>
            ))}

            <BottomNav householdId={household.id} active="history" />

            {/* Reusable Create Transaction Drawer */}
            <TransactionDrawer
                open={isDrawerOpen}
                onOpenChange={setIsDrawerOpen}
                householdId={household.id}
                mode="create"
                defaults={defaults}
                budgets={budgets}
                members={members}
                today={today}
            />

            {/* Transaction Detail & Nested Edit Drawer */}
            <TransactionDetailDrawer
                open={isDetailDrawerOpen}
                onOpenChange={setIsDetailDrawerOpen}
                householdId={household.id}
                transaction={selectedTransaction}
                locked={closed}
                canEdit={canEdit}
                budgets={budgets}
                members={members}
                defaults={defaults}
                today={today}
            />
        </>
    );
}
