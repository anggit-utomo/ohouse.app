import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowRightLeftIcon, PlusIcon } from 'lucide-react';
import { BottomNav } from '@/components/bottom-nav';
import { Button } from '@/components/ui/button';
import HeadTitle from '@/components/head-title';
import BalanceComponent from '@/components/organisms/home/balance';
import AlertBanner from '@/components/organisms/home/alert-banner';
import BudgetEmpty from '@/components/organisms/home/budget-empty';
import BudgetList from '@/components/organisms/home/budget-list';
import { GoalDrawer } from '@/components/organisms/goals/goal-drawer';
import GoalEmpty from '@/components/organisms/goals/goal-empty';
import GoalList from '@/components/organisms/goals/goal-list';
import { rupiah } from '@/lib/money';
import { TransactionDrawer } from '@/components/organisms/transactions/transaction-drawer';
import { MoveMoneyDrawer } from '@/components/organisms/transfers/move-money-drawer';
import type {
    BudgetSummary,
    GoalSummary,
    HouseholdRef,
    MemberOption,
    SweepBudget,
    TransactionDefaults,
    TransferDefaults,
} from '@/types';

type Props = {
    household: HouseholdRef;
    monthLabel: string;
    periodRange: string;
    summary: { left: number; spent: number; available: number; pct: number };
    banner: { days: number } | null;
    budgets: BudgetSummary[];
    goals: GoalSummary[];
    saved: number;
    sweepBudgets: SweepBudget[];
    members: MemberOption[];
    canEdit: boolean;
    defaults: TransactionDefaults;
    today: string;
};

export default function Home({
    household,
    monthLabel,
    periodRange,
    summary,
    banner,
    budgets,
    goals,
    saved,
    sweepBudgets,
    members,
    canEdit,
    defaults,
    today,
}: Props) {
    const base = `/h/${household.id}`;
    const [isTransactionDrawerOpen, setIsTransactionDrawerOpen] =
        useState(false);
    const [isMoveDrawerOpen, setIsMoveDrawerOpen] = useState(false);
    const [isGoalDrawerOpen, setIsGoalDrawerOpen] = useState(false);
    const [moveDefaults, setMoveDefaults] = useState<TransferDefaults>({
        from_budget_id: '',
        to_budget_id: '',
        amount: '',
        note: '',
    });

    const openMoveDrawer = (toBudgetId?: number) => {
        setMoveDefaults({
            from_budget_id: '',
            to_budget_id: toBudgetId ? String(toBudgetId) : '',
            amount: '',
            note: '',
        });
        setIsMoveDrawerOpen(true);
    };

    return (
        <>
            <Head title="Home" />

            <HeadTitle
                left={
                    <>
                        <h1 className="text-xl font-semibold">{monthLabel}</h1>
                        <p className="text-xs text-muted-foreground">
                            {periodRange}
                        </p>
                    </>
                }
                right={members.slice(0, 3).map((m) => (
                    <span
                        key={m.id}
                        title={m.name}
                        className="flex size-8 items-center justify-center rounded-full border-2 border-background bg-muted text-xs font-semibold"
                    >
                        {m.name.charAt(0).toUpperCase()}
                    </span>
                ))}
            />

            <BalanceComponent base={base} summary={summary} />

            <Button
                type="button"
                onClick={() => setIsTransactionDrawerOpen(true)}
            >
                <PlusIcon className="mr-1 size-4" /> Transaction
            </Button>

            {banner && <AlertBanner base={base} banner={banner} />}

            <HeadTitle
                className="mt-2"
                left={<h2 className="text-base font-semibold">Budgets</h2>}
                right={
                    canEdit && (
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                type="button"
                                className="text-xs"
                                onClick={() => openMoveDrawer()}
                            >
                                <ArrowRightLeftIcon className="size-4" /> Move
                                money
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                render={
                                    <Link
                                        href={`${base}/budgets`}
                                        className="text-xs font-medium"
                                    />
                                }
                            >
                                Edit
                            </Button>
                        </div>
                    )
                }
            />

            {budgets.length === 0 ? (
                <BudgetEmpty base={base} canEdit={canEdit} />
            ) : (
                <BudgetList
                    budgets={budgets}
                    base={base}
                    canEdit={canEdit}
                    onMoveMoney={(budgetId) => openMoveDrawer(budgetId)}
                />
            )}

            <div className="mt-2 flex items-center justify-between">
                <h2 className="text-base font-semibold">Goals</h2>
                <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">
                        {rupiah(saved)} saved
                    </span>
                    {canEdit && (
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            className="text-xs"
                            onClick={() => setIsGoalDrawerOpen(true)}
                        >
                            <PlusIcon className="size-4" /> Goal
                        </Button>
                    )}
                </div>
            </div>

            {goals.length === 0 ? (
                <GoalEmpty
                    canEdit={canEdit}
                    onNew={() => setIsGoalDrawerOpen(true)}
                />
            ) : (
                <GoalList goals={goals} base={base} />
            )}

            <BottomNav householdId={household.id} active="home" />

            <TransactionDrawer
                open={isTransactionDrawerOpen}
                onOpenChange={setIsTransactionDrawerOpen}
                householdId={household.id}
                defaults={defaults}
                budgets={budgets}
                members={members}
                today={today}
            />

            <MoveMoneyDrawer
                key={moveDefaults.to_budget_id}
                open={isMoveDrawerOpen}
                onOpenChange={setIsMoveDrawerOpen}
                householdId={household.id}
                budgets={budgets}
                defaults={moveDefaults}
            />

            <GoalDrawer
                open={isGoalDrawerOpen}
                onOpenChange={setIsGoalDrawerOpen}
                householdId={household.id}
                sweepBudgets={sweepBudgets}
            />
        </>
    );
}
