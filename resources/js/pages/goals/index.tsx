import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PlusIcon } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { GoalDrawer } from '@/components/organisms/goals/goal-drawer';
import GoalEmpty from '@/components/organisms/goals/goal-empty';
import GoalList from '@/components/organisms/goals/goal-list';
import { rupiah } from '@/lib/money';
import type { GoalArchived, GoalSummary, HouseholdRef } from '@/types';

type Props = {
    household: HouseholdRef;
    goals: GoalSummary[];
    archived: GoalArchived[];
    saved: number;
    canEdit: boolean;
};

const archivedOn = (date: string) =>
    new Date(date).toLocaleDateString('en-GB', {
        month: 'short',
        year: 'numeric',
    });

export default function GoalsIndex({
    household,
    goals,
    archived,
    saved,
    canEdit,
}: Props) {
    const base = `/h/${household.id}`;
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);

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
                    <GoalList goals={goals} base={base} />
                </div>
            )}

            {archived.length > 0 && (
                <div className="grid gap-2">
                    <h2 className="text-base font-semibold">History</h2>
                    {archived.map((goal) => (
                        <Link
                            key={goal.id}
                            href={`${base}/goals/${goal.id}`}
                            className="block rounded-xl border p-3"
                        >
                            <div className="flex items-baseline justify-between gap-2">
                                <span className="font-semibold">
                                    {goal.name}
                                </span>
                                {goal.archived_at && (
                                    <span className="text-xs text-muted-foreground">
                                        Archived {archivedOn(goal.archived_at)}
                                    </span>
                                )}
                            </div>
                            <div className="mt-1 flex justify-between text-xs text-muted-foreground">
                                <span>{rupiah(goal.saved)} saved</span>
                                <span>of {rupiah(goal.target)}</span>
                            </div>
                        </Link>
                    ))}
                </div>
            )}

            <GoalDrawer
                key="new"
                open={isDrawerOpen}
                onOpenChange={setIsDrawerOpen}
                householdId={household.id}
            />
        </>
    );
}
