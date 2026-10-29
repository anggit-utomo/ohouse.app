import { Head, Link } from '@inertiajs/react';
import { ArrowUpDown, ChevronRight } from 'lucide-react';
import { BottomNav } from '@/components/bottom-nav';
import { BudgetBar } from '@/components/budget-bar';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/money';

type Budget = {
    id: number;
    name: string;
    left: number;
    spent: number;
    available: number;
    pct: number;
    over: boolean;
};

type Props = {
    household: { id: number };
    monthLabel: string;
    summary: { left: number; spent: number; available: number; pct: number };
    banner: { daysLeft: number } | null;
    budgets: Budget[];
    members: { id: number; name: string }[];
    canEdit: boolean;
};

const endsText = (days: number) =>
    days === 0
        ? 'ends today'
        : days === 1
          ? 'ends tomorrow'
          : `ends in ${days} days`;
const leftText = (left: number) =>
    left < 0 ? `Over by ${rupiah(-left)}` : `${rupiah(left)} left`;

export default function Home({
    household,
    monthLabel,
    summary,
    banner,
    budgets,
    members,
    canEdit,
}: Props) {
    const base = `/h/${household.id}`;

    return (
        <>
            <Head title="Home" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-3 p-4 pb-28">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{monthLabel}</h1>
                    <div className="flex -space-x-2">
                        {members.slice(0, 3).map((m) => (
                            <span
                                key={m.id}
                                title={m.name}
                                className="flex size-8 items-center justify-center rounded-full border-2 border-background bg-muted text-xs font-semibold"
                            >
                                {m.name.charAt(0).toUpperCase()}
                            </span>
                        ))}
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="flex items-center justify-between text-muted-foreground">
                        <span>Remaining this month</span>
                        <Link
                            href={`${base}/report`}
                            className="text-sm font-semibold text-foreground"
                        >
                            View report
                        </Link>
                    </div>
                    <p
                        className={`my-1 mb-3 text-3xl font-bold ${summary.left < 0 ? 'text-destructive' : ''}`}
                    >
                        {summary.left < 0 ? '-' : ''}
                        {rupiah(Math.abs(summary.left))}
                    </p>
                    <BudgetBar pct={summary.pct} over={summary.left < 0} />
                    <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                        <span>Spent {rupiah(summary.spent)}</span>
                        <span>of {rupiah(summary.available)}</span>
                    </div>
                </div>

                {banner && (
                    <div className="flex items-center justify-between gap-3 rounded-xl border p-3">
                        <div>
                            <p className="font-semibold">
                                {monthLabel.split(' ')[0]}{' '}
                                {endsText(banner.daysLeft)}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Carry over or reset each budget
                            </p>
                        </div>
                        <Button size="sm" variant="outline" asChild>
                            <Link href={`${base}/close`}>Review</Link>
                        </Button>
                    </div>
                )}

                <div className="mt-2 flex items-center justify-between">
                    <h2 className="text-base font-semibold">Budgets</h2>
                    {canEdit && (
                        <div className="flex items-center gap-3">
                            <Button size="sm" variant="outline" asChild>
                                <Link href={`${base}/transfers/create`}>
                                    <ArrowUpDown className="size-4" /> Move
                                    money
                                </Link>
                            </Button>
                            <Link
                                href={`${base}/budgets`}
                                className="text-sm font-medium"
                            >
                                Edit
                            </Link>
                        </div>
                    )}
                </div>

                {budgets.length === 0 && (
                    <div className="rounded-xl border p-6 text-center">
                        <p className="font-semibold">No budgets yet</p>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Set an amount for food, fuel and the rest to start
                            tracking.
                        </p>
                        {canEdit && (
                            <Button asChild>
                                <Link href={`${base}/budgets`}>
                                    Set budgets
                                </Link>
                            </Button>
                        )}
                    </div>
                )}

                {budgets.map((b) => (
                    <div key={b.id} className="py-1">
                        <Link
                            href={`${base}/budgets/${b.id}`}
                            className="block"
                        >
                            <div className="mb-2 flex items-center justify-between">
                                <span className="flex items-center gap-0.5 font-semibold">
                                    {b.name}
                                    <ChevronRight className="size-4 text-muted-foreground" />
                                </span>
                                <span
                                    className={`text-sm ${b.over ? 'text-destructive' : 'text-muted-foreground'}`}
                                >
                                    {leftText(b.left)}
                                </span>
                            </div>
                            <BudgetBar pct={b.pct} over={b.over} />
                        </Link>
                        {b.over && canEdit && (
                            <div className="mt-2 flex justify-between text-xs">
                                <span className="text-muted-foreground">
                                    Cover it from another budget
                                </span>
                                <Link
                                    href={`${base}/transfers/create?to=${b.id}`}
                                    className="font-semibold"
                                >
                                    Move money
                                </Link>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            <BottomNav householdId={household.id} active="home" />
        </>
    );
}
