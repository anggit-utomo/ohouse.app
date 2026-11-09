import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PlusIcon } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { GoalDetailDrawer } from '@/components/organisms/goals/goal-detail-drawer';
import { GoalDrawer } from '@/components/organisms/goals/goal-drawer';
import GoalEmpty from '@/components/organisms/goals/goal-empty';
import GoalHistoryItem from '@/components/organisms/goals/goal-history-item';
import GoalList from '@/components/organisms/goals/goal-list';
import { rupiah } from '@/lib/money';
import type {
    BudgetOption,
    GoalArchived,
    GoalDetail,
    GoalSummary,
    HouseholdRef,
    SweepBudget,
} from '@/types';

type Props = {
    household: HouseholdRef;
    goals: GoalDetail[];
    archived: GoalArchived[];
    saved: number;
    sweepBudgets: SweepBudget[];
    budgets: BudgetOption[];
    today: string;
    canEdit: boolean;
};

export default function GoalsIndex({
    household,
    goals,
    archived,
    saved,
    sweepBudgets,
    budgets,
    today,
    canEdit,
}: Props) {
    const base = `/h/${household.id}`;
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [selectedGoalId, setSelectedGoalId] = useState<number | null>(null);
    const [isDetailOpen, setIsDetailOpen] = useState(false);

    const selectedGoal =
        [...goals, ...archived].find((goal) => goal.id === selectedGoalId) ??
        null;

    const openGoal = (goal: GoalSummary) => {
        setSelectedGoalId(goal.id);
        setIsDetailOpen(true);
    };

    return (
        <>
            <Head title="Goals" />

            <PageHeader
                title="Goals"
                href={base}
                action={
                    canEdit && (
                        <Button
                            size="sm"
                            type="button"
                            onClick={() => setIsDrawerOpen(true)}
                        >
                            <PlusIcon className="size-4" /> New goal
                        </Button>
                    )
                }
            />

            <Card>
                <CardHeader>
                    <span className="text-sm text-muted-foreground">
                        Saved toward goals
                    </span>
                    <CardTitle className="text-3xl font-bold">
                        {rupiah(saved)}
                    </CardTitle>
                </CardHeader>
                <CardContent />
            </Card>

            {goals.length === 0 ? (
                <GoalEmpty
                    canEdit={canEdit}
                    onNew={() => setIsDrawerOpen(true)}
                />
            ) : (
                <div className="grid gap-2">
                    <GoalList goals={goals} base={base} onSelect={openGoal} />
                </div>
            )}

            {archived.length > 0 && (
                <div className="grid gap-2">
                    <h2 className="text-base font-semibold">History</h2>
                    {archived.map((goal) => (
                        <GoalHistoryItem
                            key={goal.id}
                            goal={goal}
                            onSelect={openGoal}
                        />
                    ))}
                </div>
            )}

            <GoalDrawer
                open={isDrawerOpen}
                onOpenChange={setIsDrawerOpen}
                householdId={household.id}
                sweepBudgets={sweepBudgets}
            />

            <GoalDetailDrawer
                open={isDetailOpen}
                onOpenChange={setIsDetailOpen}
                householdId={household.id}
                goal={selectedGoal}
                canEdit={canEdit}
                budgets={budgets}
                today={today}
            />
        </>
    );
}
