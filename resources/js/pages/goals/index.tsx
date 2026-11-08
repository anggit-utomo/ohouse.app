import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PlusIcon } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { GoalDrawer } from '@/components/organisms/goals/goal-drawer';
import GoalEmpty from '@/components/organisms/goals/goal-empty';
import GoalList from '@/components/organisms/goals/goal-list';
import { rupiah } from '@/lib/money';
import type { GoalSummary, HouseholdRef } from '@/types';

type Props = {
    household: HouseholdRef;
    goals: GoalSummary[];
    saved: number;
    canEdit: boolean;
};

export default function GoalsIndex({
    household,
    goals,
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

            <GoalDrawer
                key="new"
                open={isDrawerOpen}
                onOpenChange={setIsDrawerOpen}
                householdId={household.id}
            />
        </>
    );
}
