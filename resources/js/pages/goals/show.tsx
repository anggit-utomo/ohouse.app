import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    CheckIcon,
    MinusIcon,
    PencilIcon,
    PlusIcon,
    Trash2,
} from 'lucide-react';
import { BudgetBar } from '@/components/budget-bar';
import { PageHeader } from '@/components/page-header';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { GoalContributionDrawer } from '@/components/organisms/goals/goal-contribution-drawer';
import { GoalDrawer } from '@/components/organisms/goals/goal-drawer';
import { GoalWithdrawalDrawer } from '@/components/organisms/goals/goal-withdrawal-drawer';
import { rupiah } from '@/lib/money';
import { setIfNotNull, cn } from '@/lib/utils';
import type {
    BudgetOption,
    GoalDetail,
    GoalMovementRow,
    GoalSaving,
    HouseholdRef,
} from '@/types';

const OUTSIDE = 'outside';

type Props = {
    household: HouseholdRef;
    goal: GoalDetail;
    movements: GoalMovementRow[];
    savings: GoalSaving[];
    budgets: BudgetOption[];
    today: string;
    archived: boolean;
    canEdit: boolean;
};

export default function ShowGoal({
    household,
    goal,
    movements,
    savings,
    budgets,
    today,
    archived,
    canEdit,
}: Props) {
    const base = `/h/${household.id}`;
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [isContributionOpen, setIsContributionOpen] = useState(false);
    const [isWithdrawalOpen, setIsWithdrawalOpen] = useState(false);
    const [isCompleteOpen, setIsCompleteOpen] = useState(false);
    const [destination, setDestination] = useState(OUTSIDE);
    const [pendingMovement, setPendingMovement] =
        useState<GoalMovementRow | null>(null);

    const pendingLabel =
        pendingMovement?.type === 'withdrawal' ? 'withdrawal' : 'contribution';

    const removeMovement = () => {
        if (!pendingMovement) return;

        router.delete(
            `${base}/goals/${goal.id}/movements/${pendingMovement.id}`,
            {
                preserveScroll: true,
                onSuccess: () => setPendingMovement(null),
            },
        );
    };

    const completeGoal = () => {
        router.post(
            `${base}/goals/${goal.id}/complete`,
            {
                budget_id:
                    goal.balance > 0 && destination !== OUTSIDE
                        ? Number(destination)
                        : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => setIsCompleteOpen(false),
            },
        );
    };

    return (
        <>
            <Head title={goal.name} />

            <PageHeader
                title={goal.name}
                href={`${base}/goals`}
                action={
                    canEdit &&
                    !archived && (
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

            {archived && (
                <div className="rounded-xl border bg-muted/40 p-3 text-sm text-muted-foreground">
                    This goal is archived. Its history stays here and it cannot
                    be changed.
                </div>
            )}

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

            {canEdit && !archived && (
                <div className="grid gap-2">
                    <div className="grid grid-cols-2 gap-2">
                        <Button
                            type="button"
                            onClick={() => setIsContributionOpen(true)}
                        >
                            <PlusIcon className="mr-1 size-4" /> Add money
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsWithdrawalOpen(true)}
                        >
                            <MinusIcon className="mr-1 size-4" /> Take out
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setIsCompleteOpen(true)}
                    >
                        <CheckIcon className="mr-1 size-4" /> Complete goal
                    </Button>
                </div>
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
                            <span className="text-right">
                                {saving.saved > 0 && (
                                    <span className="block font-semibold">
                                        {rupiah(saving.saved)} saved
                                    </span>
                                )}
                                {saving.withdrawn > 0 && (
                                    <span className="block text-xs text-muted-foreground">
                                        {rupiah(saving.withdrawn)} taken out
                                    </span>
                                )}
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
                            {movement.note ??
                                (movement.type === 'withdrawal'
                                    ? 'Money taken out'
                                    : 'Money added')}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {movement.type === 'withdrawal'
                                ? 'Taken out'
                                : 'Added'}{' '}
                            · {movement.user} ·{' '}
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
                    {movement.can_remove && canEdit && (
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            aria-label="Remove movement"
                            onClick={() => setPendingMovement(movement)}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    )}
                </div>
            ))}

            <AlertDialog
                open={pendingMovement !== null}
                onOpenChange={(open) => {
                    if (!open) setPendingMovement(null);
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Remove this {pendingLabel}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {pendingMovement
                                ? `The ${pendingLabel} of ${rupiah(pendingMovement.amount)} will be deleted. The Goal balance and any linked Budget will go back to their previous values.`
                                : null}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={removeMovement}
                            variant="destructive"
                        >
                            Remove
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={isCompleteOpen} onOpenChange={setIsCompleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Complete {goal.name}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {goal.balance > 0
                                ? `${rupiah(goal.balance)} will leave the goal and it will move to history. This cannot be undone.`
                                : 'The goal has no balance left. It will move to history and cannot be changed.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    {goal.balance > 0 && (
                        <div className="grid gap-1.5">
                            <Label>To</Label>
                            <Select
                                value={destination}
                                onValueChange={setIfNotNull(setDestination)}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Outside the budget" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={OUTSIDE}>
                                        Outside the budget
                                    </SelectItem>
                                    {budgets.map((budget) => (
                                        <SelectItem
                                            key={budget.id}
                                            value={String(budget.id)}
                                        >
                                            {budget.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={completeGoal}>
                            Complete goal
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

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
                budgets={budgets}
                today={today}
            />

            <GoalWithdrawalDrawer
                key={today}
                open={isWithdrawalOpen}
                onOpenChange={setIsWithdrawalOpen}
                householdId={household.id}
                goalId={goal.id}
                balance={goal.balance}
                budgets={budgets}
                today={today}
            />
        </>
    );
}
