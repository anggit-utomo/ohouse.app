import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { BudgetBar } from '@/components/budget-bar';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/money';

type Props = {
    household: { id: number };
    budget: { id: number; name: string };
    monthLabel: string;
    closed: boolean;
    summary: {
        left: number;
        spent: number;
        available: number;
        pct: number;
        over: boolean;
    };
    transactions: {
        id: number;
        title: string;
        user: string;
        signed: number;
        occurred_at: string;
    }[];
    canEdit: boolean;
};

export default function ShowBudget({
    household,
    budget,
    monthLabel,
    closed,
    summary,
    transactions,
    canEdit,
}: Props) {
    const base = `/h/${household.id}`;

    return (
        <>
            <Head title={budget.name} />

            <div className="mx-auto flex w-full max-w-md flex-col gap-3 p-4 pb-10">
                <div className="flex items-center justify-between">
                    <Link
                        href={base}
                        className="flex items-center gap-1 text-lg font-semibold"
                    >
                        <ChevronLeft className="size-5" />
                        {budget.name}
                    </Link>
                    {canEdit && !closed && (
                        <Link
                            href={`${base}/budgets`}
                            className="text-sm font-medium"
                        >
                            Edit budget
                        </Link>
                    )}
                </div>

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
                </div>

                {canEdit && !closed && (
                    <div className="grid grid-cols-2 gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={`${base}/transactions/create?budget=${budget.id}`}
                            >
                                Add expense
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={`${base}/transfers/create?from=${budget.id}`}
                            >
                                Move money
                            </Link>
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
                    <Link
                        key={t.id}
                        href={`${base}/transactions/${t.id}`}
                        className="flex items-center gap-3 py-2"
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
                    </Link>
                ))}
            </div>
        </>
    );
}
