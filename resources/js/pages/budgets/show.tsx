import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { BudgetBar } from '@/components/budget-bar';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/money';
import { toTransactionDetail } from '@/lib/transactions';
import HeadTitle from '@/components/head-title';
import { TransactionDrawer } from '@/components/organisms/transactions/transaction-drawer';
import { MoveMoneyDrawer } from '@/components/organisms/transfers/move-money-drawer';
import { TransactionDetailDrawer } from '@/components/organisms/transactions/transaction-detail-drawer';
import type {
    BudgetOption,
    HouseholdRef,
    MemberOption,
    TransactionDefaults,
    TransactionDetail,
    TransactionRow,
} from '@/types';

type Props = {
    household: HouseholdRef;
    budget: { id: number; name: string };
    monthLabel: string;
    closed: boolean;
    summary: {
        left: number;
        spent: number;
        available: number;
        moved: number;
        pct: number;
        over: boolean;
    };
    transactions: TransactionRow[];
    canEdit: boolean;
    budgets: BudgetOption[];
    members: MemberOption[];
    defaults: TransactionDefaults;
    today: string;
};

export default function ShowBudget({
    household,
    budget,
    monthLabel,
    closed,
    summary,
    transactions,
    canEdit,
    budgets,
    members,
    defaults,
    today,
}: Props) {
    const base = `/h/${household.id}`;

    const [isTransactionDrawerOpen, setIsTransactionDrawerOpen] =
        useState(false);
    const [isMoveDrawerOpen, setIsMoveDrawerOpen] = useState(false);
    const [selectedTransaction, setSelectedTransaction] =
        useState<TransactionDetail | null>(null);
    const [isDetailDrawerOpen, setIsDetailDrawerOpen] = useState(false);

    const handleOpenDetail = (row: TransactionRow) => {
        setSelectedTransaction(toTransactionDetail(row));
        setIsDetailDrawerOpen(true);
    };

    return (
        <>
            <Head title={budget.name} />

            <HeadTitle
                left={
                    <Link
                        href={base}
                        className="flex items-center gap-1 text-lg font-semibold"
                    >
                        <ChevronLeft className="size-5" />
                        {budget.name}
                    </Link>
                }
                right={
                    canEdit &&
                    !closed && (
                        <Link
                            href={`${base}/budgets`}
                            className="text-sm font-medium"
                        >
                            Edit budget
                        </Link>
                    )
                }
            />

            <div className="rounded-xl border p-4">
                <p className="text-muted-foreground">
                    {closed ? `Left in ${monthLabel}` : 'Left this month'}
                </p>
                <p
                    className={`my-1 mb-3 text-3xl font-bold ${summary.over ? 'text-destructive' : ''}`}
                >
                    {summary.over ? '-' : ''}
                    {rupiah(Math.abs(summary.left))}
                </p>
                <BudgetBar pct={summary.pct} over={summary.over} />
                <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                    <span>Spent {rupiah(summary.spent)}</span>
                    <span>of {rupiah(summary.available)}</span>
                </div>
                {summary.moved > 0 && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        Moved {rupiah(summary.moved)} to goals
                    </p>
                )}
            </div>

            {canEdit && !closed && (
                <div className="grid grid-cols-2 gap-2">
                    <Button
                        variant="outline"
                        type="button"
                        onClick={() => setIsTransactionDrawerOpen(true)}
                    >
                        Add expense
                    </Button>
                    <Button
                        variant="outline"
                        type="button"
                        onClick={() => setIsMoveDrawerOpen(true)}
                    >
                        Move money
                    </Button>
                </div>
            )}

            <h2 className="mt-3 text-base font-semibold">Transactions</h2>
            {transactions.length === 0 && (
                <p className="py-6 text-center text-muted-foreground">
                    Nothing yet this month.
                </p>
            )}

            {transactions.map((t) => (
                <button
                    key={t.id}
                    type="button"
                    className="flex w-full items-center gap-3 py-2 text-left"
                    onClick={() => handleOpenDetail(t)}
                >
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                        {t.user.charAt(0).toUpperCase()}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate font-semibold">
                            {t.title}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {new Date(t.occurred_at).toLocaleDateString(
                                'en-GB',
                                { day: 'numeric', month: 'short' },
                            )}
                        </span>
                    </span>
                    <span
                        className={`font-semibold ${t.signed > 0 ? 'text-green-700' : ''}`}
                    >
                        {t.signed > 0 ? '+' : '-'}
                        {rupiah(Math.abs(t.signed))}
                    </span>
                </button>
            ))}

            {/* Create Expense Drawer */}
            <TransactionDrawer
                open={isTransactionDrawerOpen}
                onOpenChange={setIsTransactionDrawerOpen}
                householdId={household.id}
                mode="create"
                defaults={{
                    ...defaults,
                    budget_id: String(budget.id),
                }}
                budgets={budgets}
                members={members}
                today={today}
            />

            {/* Move Money Drawer */}
            <MoveMoneyDrawer
                open={isMoveDrawerOpen}
                onOpenChange={setIsMoveDrawerOpen}
                householdId={household.id}
                budgets={budgets}
                defaults={{
                    from_budget_id: String(budget.id),
                    to_budget_id: '',
                    amount: '',
                    note: '',
                }}
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
