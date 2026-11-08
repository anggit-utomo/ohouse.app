import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PencilIcon, PlusIcon } from 'lucide-react';
import { BudgetBar } from '@/components/budget-bar';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { GoalContributionDrawer } from '@/components/organisms/goals/goal-contribution-drawer';
import { GoalDrawer } from '@/components/organisms/goals/goal-drawer';
import { rupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import type {
    GoalDetail,
    GoalMovementRow,
    GoalSaving,
    HouseholdRef,
} from '@/types';

type Props = {
    household: HouseholdRef;
    goal: GoalDetail;
    movements: GoalMovementRow[];
    savings: GoalSaving[];
    today: string;
    canEdit: boolean;
};

export default function ShowGoal({
    household,
    goal,
    movements,
    savings,
    today,
    canEdit,
}: Props) {
    const base = `/h/${household.id}`;
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [isContributionOpen, setIsContributionOpen] = useState(false);

    return (
        <>
            <Head title={goal.name} />

            <PageHeader
                title={goal.name}
                href={`${base}/goals`}
                action={
                    canEdit && (
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            onClick={() => setIsDrawerOpen(true)}
                        >
                            <PencilIcon className="size-4" /> Edit
                        </Button>
                    )
                }
            />

            <div className="rounded-xl border p-4">
                <p className="text-muted-foreground">
                    {goal.over ? 'Over target' : 'Saved so far'}
                </p>
                <p
                    className={cn(
                        'my-1 mb-3 text-3xl font-bold',
                        goal.over && 'text-destructive',
                    )}
                >
                    {rupiah(goal.balance)}
                </p>
                <BudgetBar pct={Math.min(goal.pct, 100)} over={goal.over} />
                <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                    <span>{goal.pct}% of target</span>
                    <span>of {rupiah(goal.target)}</span>
                </div>
            </div>

            <div className="grid gap-1 text-sm">
                {goal.over ? (
                    <p className="text-muted-foreground">
                        That is {rupiah(goal.balance - goal.target)} past the
                        target.
                    </p>
                ) : (
                    <p className="text-muted-foreground">
                        {rupiah(goal.remaining)} to go.
                    </p>
                )}
                {goal.target_date && (
                    <p className="text-muted-foreground">
                        Deadline{' '}
                        {new Date(goal.target_date).toLocaleDateString(
                            'en-GB',
                            {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                            },
                        )}
                    </p>
                )}
            </div>

            {canEdit && (
                <Button
                    type="button"
                    onClick={() => setIsContributionOpen(true)}
                >
                    <PlusIcon className="mr-1 size-4" /> Add money
                </Button>
            )}

            {savings.length > 0 && (
                <div className="rounded-xl border p-3 text-sm">
                    <p className="mb-1 font-semibold">Saved by period</p>
                    {savings.map((saving) => (
                        <div
                            key={saving.period_start}
                            className="flex justify-between py-0.5"
                        >
                            <span className="text-muted-foreground">
                                {saving.label}
                            </span>
                            <span className="font-semibold">
                                {rupiah(saving.saved)}
                            </span>
                        </div>
                    ))}
                </div>
            )}

            <h2 className="text-base font-semibold">Movements</h2>

            {movements.length === 0 && (
                <p className="py-6 text-center text-muted-foreground">
                    No money saved yet.
                </p>
            )}

            {movements.map((movement) => (
                <div key={movement.id} className="flex items-center gap-3 py-2">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                        {movement.user.charAt(0).toUpperCase()}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate font-semibold">
                            {movement.note ?? 'Money added'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {movement.user} ·{' '}
                            {new Date(movement.occurred_at).toLocaleDateString(
                                'en-GB',
                                {
                                    day: 'numeric',
                                    month: 'short',
                                },
                            )}
                        </span>
                    </span>
                    <span
                        className={cn(
                            'font-semibold',
                            movement.signed > 0 && 'text-green-700',
                        )}
                    >
                        {movement.signed > 0 ? '+' : '-'}
                        {rupiah(Math.abs(movement.signed))}
                    </span>
                </div>
            ))}

            <GoalDrawer
                key={goal.id}
                open={isDrawerOpen}
                onOpenChange={setIsDrawerOpen}
                householdId={household.id}
                goal={goal}
            />

            <GoalContributionDrawer
                key={today}
                open={isContributionOpen}
                onOpenChange={setIsContributionOpen}
                householdId={household.id}
                goalId={goal.id}
                today={today}
            />
        </>
    );
}
