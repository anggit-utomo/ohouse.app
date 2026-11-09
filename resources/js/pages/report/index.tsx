import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { rupiah } from '@/lib/money';
import { BottomNav } from '@/components/bottom-nav';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import HeadTitle from '@/components/head-title';
import type { HouseholdRef, MonthOption } from '@/types';

type BudgetItem = {
    id: number;
    name: string;
    spent: number;
    pct: number;
    changePct?: number | null;
};

type PersonItem = {
    id: number;
    name: string;
    spent: number;
    pct: number;
    initial?: string;
};

type ExpenseItem = {
    id: number;
    name: string;
    category: string;
    date: string;
    amount: number | null;
    initial?: string;
};

type DailyItem = {
    date: string;
    amount: number;
    label: string;
};

type Props = {
    household: HouseholdRef;
    month: string;
    months: MonthOption[];
    hasData: boolean;
    spent: number;
    income: number;
    previous: { label: string; spent: number } | null;
    change: number | null;
    dailySpending?: DailyItem[];
    busiestDay?: string | null;
    budgets?: BudgetItem[];
    biggestExpenses?: ExpenseItem[];
    people?: PersonItem[];
};

// Color mapping derived from your theme chart variables & primary teal shades
const chartColors = [
    'var(--primary)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

export default function Report({
    household,
    month,
    months,
    hasData,
    spent,
    income,
    previous,
    change,
    dailySpending = [],
    busiestDay,
    budgets = [],
    biggestExpenses = [],
    people = [],
}: Props) {
    const base = `/h/${household.id}`;
    const [hoveredBudget, setHoveredBudget] = useState<BudgetItem | null>(null);

    const maxDailySpent = Math.max(...dailySpending.map((d) => d.amount), 1);
    const totalBudgetSpent = budgets.reduce((sum, b) => sum + b.spent, 0) || 1;

    // Dynamic Donut SVG rendering using theme OKLCH chart tokens
    const renderDonutSlices = () => {
        let accumulated = 0;

        return budgets.map((b, i) => {
            const share = (b.spent / totalBudgetSpent) * 100;
            const start = accumulated;
            accumulated += share;

            return (
                <circle
                    key={b.id}
                    cx="18"
                    cy="18"
                    r="15.91549430918954"
                    fill="transparent"
                    stroke={chartColors[i % chartColors.length]}
                    strokeWidth="4.5"
                    strokeDasharray={`${share} ${100 - share}`}
                    strokeDashoffset={-start}
                    className="cursor-pointer transition-opacity duration-300 hover:opacity-80"
                    onMouseEnter={() => setHoveredBudget(b)}
                    onMouseLeave={() => setHoveredBudget(null)}
                    onClick={() => setHoveredBudget(b)}
                />
            );
        });
    };

    const activeBudget = hoveredBudget || budgets[0];

    return (
        <>
            <Head title="Report" />

            <HeadTitle
                left={<h1 className="text-xl font-semibold">Report</h1>}
            />

            {/* Period Selector */}
            <Select
                value={month}
                onValueChange={(m) =>
                    router.get(
                        `${base}/report`,
                        { month: m },
                        { preserveScroll: true },
                    )
                }
            >
                <SelectTrigger className="w-full">
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

            {!hasData ? (
                <Card>
                    <CardContent className="py-12 text-center text-muted-foreground">
                        Nothing recorded in this month yet.
                    </CardContent>
                </Card>
            ) : (
                <>
                    {/* 1. Total Spent Summary & Mini Bar Chart */}
                    <Card>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Total spent
                                </span>
                                {change !== null && previous && (
                                    <div
                                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${change <= 0
                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                : 'bg-destructive/10 text-destructive'
                                            }`}
                                    >
                                        <span>
                                            {change <= 0 ? '↓' : '↑'}{' '}
                                            {rupiah(Math.abs(change))} vs{' '}
                                            {previous.label}
                                        </span>
                                    </div>
                                )}
                            </div>

                            <div className="text-2xl font-semibold">
                                {rupiah(spent)}
                            </div>

                            {income > 0 && (
                                <p className="mt-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    Income {rupiah(income)}
                                </p>
                            )}

                            {/* Daily Mini Bar Chart */}
                            {dailySpending.length > 0 && (
                                <div className="mt-4">
                                    <div className="flex h-14 items-end justify-between gap-1 px-1">
                                        {dailySpending.map((day, idx) => {
                                            const heightPct = Math.max(
                                                (day.amount / maxDailySpent) *
                                                100,
                                                8,
                                            );
                                            const isHighest =
                                                day.amount === maxDailySpent;
                                            return (
                                                <div
                                                    key={idx}
                                                    className="group relative flex flex-1 flex-col items-center"
                                                >
                                                    <div
                                                        className={`w-full rounded-sm transition-all ${isHighest
                                                                ? 'bg-primary'
                                                                : 'bg-muted group-hover:bg-primary/50'
                                                            }`}
                                                        style={{
                                                            height: `${heightPct}%`,
                                                        }}
                                                        title={`${day.label}: ${rupiah(day.amount)}`}
                                                    />
                                                </div>
                                            );
                                        })}
                                    </div>
                                    <div className="mt-2 flex items-center justify-between px-0.5 text-[11px] font-medium text-muted-foreground">
                                        <span>
                                            {dailySpending[0]?.label || 'Start'}
                                        </span>
                                        {busiestDay && (
                                            <span>
                                                Busiest day · {busiestDay}
                                            </span>
                                        )}
                                        <span>Today</span>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* 2. Spending By Budget Card */}
                    {budgets.length > 0 && (
                        <Card>
                            <CardContent>
                                <h2 className="text-base font-semibold">
                                    By budget
                                </h2>

                                {/* Donut Chart */}
                                <div className="relative flex items-center justify-center p-4">
                                    <svg
                                        viewBox="0 0 36 36"
                                        className="h-44 w-44 -rotate-90 transform p-2"
                                    >
                                        {renderDonutSlices()}
                                    </svg>
                                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                                        <span className="text-base font-bold text-foreground">
                                            {activeBudget?.name}
                                        </span>
                                        <span className="text-xs font-medium text-muted-foreground">
                                            {activeBudget?.pct}% of spending
                                        </span>
                                    </div>
                                </div>

                                {/* Budget Items */}
                                <div className="space-y-3.5 pt-1">
                                    {budgets.map((b, i) => (
                                        <div
                                            key={b.id}
                                            className="flex items-center justify-between text-sm"
                                        >
                                            <div className="flex items-center gap-2.5">
                                                <span
                                                    className="size-2.5 shrink-0 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            chartColors[
                                                            i %
                                                            chartColors.length
                                                            ],
                                                    }}
                                                />
                                                <span className="font-medium text-foreground">
                                                    {b.name}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <span className="font-bold text-foreground">
                                                    {rupiah(b.spent)}
                                                </span>
                                                <span className="w-8 text-right text-xs font-medium text-muted-foreground">
                                                    {b.pct}%
                                                </span>
                                                {b.changePct != null && (
                                                    <div
                                                        className={`flex w-12 items-center justify-end text-xs font-semibold ${(b.changePct ?? 0) >
                                                                0
                                                                ? 'text-destructive'
                                                                : (b.changePct ??
                                                                    0) < 0
                                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                                    : 'text-muted-foreground'
                                                            }`}
                                                    >
                                                        {(b.changePct ?? 0) >
                                                            0 && (
                                                                <ArrowUpRight className="mr-0.5 size-3.5" />
                                                            )}
                                                        {(b.changePct ?? 0) <
                                                            0 && (
                                                                <ArrowDownRight className="mr-0.5 size-3.5" />
                                                            )}
                                                        {(b.changePct ?? 0) ===
                                                            0 && (
                                                                <Minus className="mr-0.5 size-3" />
                                                            )}
                                                        {Math.abs(
                                                            b.changePct ?? 0,
                                                        )}
                                                        %
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {previous && (
                                    <p className="pt-1 text-[11px] text-muted-foreground">
                                        Arrows compare with {previous.label}.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* 3. Biggest Expenses */}
                    {biggestExpenses.length > 0 && (
                        <Card>
                            <CardContent>
                                <h2 className="text-base font-semibold">
                                    Biggest expenses
                                </h2>
                                <div className="space-y-4">
                                    {biggestExpenses.map((exp) => (
                                        <div
                                            key={exp.id}
                                            className="flex items-center justify-between"
                                        >
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-9 border border-border bg-muted text-xs font-medium text-foreground">
                                                    <AvatarFallback>
                                                        {exp.initial ||
                                                            exp.name.charAt(0)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="text-sm leading-tight font-semibold text-foreground">
                                                        {exp.name}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {exp.category} ·{' '}
                                                        {exp.date}
                                                    </p>
                                                </div>
                                            </div>
                                            <span className="text-sm font-bold text-foreground">
                                                {exp.amount === null
                                                    ? '—'
                                                    : `-${rupiah(exp.amount)}`}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* 4. You and your partner */}
                    {people.length > 0 && (
                        <div className="space-y-3">
                            <h2 className="px-1 text-base font-bold text-foreground">
                                You and your partner
                            </h2>
                            <div className="grid gap-3 grid-cols-2">
                                {people.map((p) => (
                                    <Card key={p.id}>
                                        <CardContent>
                                            <div className="flex items-center gap-2.5">
                                                <Avatar className="size-8 bg-primary text-xs font-medium text-primary-foreground">
                                                    <AvatarFallback className="bg-primary text-primary-foreground">
                                                        {p.initial ||
                                                            p.name
                                                                .charAt(0)
                                                                .toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span className="text-sm font-semibold text-foreground">
                                                    {p.name}
                                                </span>
                                            </div>

                                            <div>
                                                <div className="text-base font-extrabold text-foreground">
                                                    {rupiah(p.spent)}
                                                </div>
                                                {/* Progress fill bar */}
                                                <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full bg-primary transition-all duration-300"
                                                        style={{
                                                            width: `${Math.min(
                                                                Math.max(
                                                                    p.pct,
                                                                    0,
                                                                ),
                                                                100,
                                                            )}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="mt-1.5 block text-xs font-medium text-muted-foreground">
                                                    {p.pct}% of spending
                                                </span>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    )}
                </>
            )}

            <BottomNav householdId={household.id} active="report" />
        </>
    );
}
