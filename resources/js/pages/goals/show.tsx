import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PencilIcon } from 'lucide-react';
import { BudgetBar } from '@/components/budget-bar';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { GoalDrawer } from '@/components/organisms/goals/goal-drawer';
import { rupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { GoalDetail, HouseholdRef } from '@/types';

type Props = {
    household: HouseholdRef;
    goal: GoalDetail;
    canEdit: boolean;
};

export default function ShowGoal({ household, goal, canEdit }: Props) {
    const base = `/h/${household.id}`;
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);

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

            <h2 className="mt-3 text-base font-semibold">Activity</h2>
            <p className="py-6 text-center text-muted-foreground">
                No money saved yet.
            </p>

            <GoalDrawer
                key={goal.id}
                open={isDrawerOpen}
                onOpenChange={setIsDrawerOpen}
                householdId={household.id}
                goal={goal}
            />
        </>
    );
}
