import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    CheckIcon,
    MinusIcon,
    PencilIcon,
    PlusIcon,
    Trash2,
} from 'lucide-react';
import { BudgetBar } from '@/components/budget-bar';
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
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { GoalContributionDrawer } from '@/components/organisms/goals/goal-contribution-drawer';
import { GoalDrawer } from '@/components/organisms/goals/goal-drawer';
import { GoalWithdrawalDrawer } from '@/components/organisms/goals/goal-withdrawal-drawer';
import { rupiah } from '@/lib/money';
import { cn, setIfNotNull } from '@/lib/utils';
import type { BudgetOption, GoalDetail, GoalMovementRow } from '@/types';

const OUTSIDE = 'outside';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    householdId: number;
    goal: GoalDetail | null;
    canEdit: boolean;
    budgets: BudgetOption[];
    today: string;
};

export function GoalDetailDrawer({
    open,
    onOpenChange,
    householdId,
    goal,
    canEdit,
    budgets,
    today,
}: Props) {
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isContributionOpen, setIsContributionOpen] = useState(false);
    const [isWithdrawalOpen, setIsWithdrawalOpen] = useState(false);
    const [isCompleteOpen, setIsCompleteOpen] = useState(false);
    const [destination, setDestination] = useState(OUTSIDE);
    const [pendingMovement, setPendingMovement] =
        useState<GoalMovementRow | null>(null);

    // Keep the Drawer root mounted while empty so the first open animates:
    // mounting a Base UI Drawer already-open skips its starting-style transition.
    if (!goal) {
        return <Drawer open={false} onOpenChange={onOpenChange} />;
    }

    const base = `/h/${householdId}`;
    const archived = goal.archived_at !== null;
    const editable = canEdit && !archived;
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
                onSuccess: () => {
                    setIsCompleteOpen(false);
                    setDestination(OUTSIDE);
                },
            },
        );
    };

    return (
        <>
            <Drawer open={open} onOpenChange={onOpenChange}>
                <DrawerContent>
                    <div className="mx-auto flex min-h-0 w-full max-w-sm flex-1 flex-col">
                        <DrawerHeader>
                            <DrawerTitle className="text-center">
                                {goal.name}
                            </DrawerTitle>
                            <DrawerDescription className="text-center">
                                {archived
                                    ? 'This goal is archived and cannot be changed.'
                                    : 'Goal details and ledger'}
                            </DrawerDescription>
                        </DrawerHeader>

                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-2">
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
                                <BudgetBar
                                    pct={Math.min(goal.pct, 100)}
                                    over={goal.over}
                                />
                                <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                                    <span>{goal.pct}% of target</span>
                                    <span>of {rupiah(goal.target)}</span>
                                </div>
                            </div>

                            <div className="grid gap-1 text-sm">
                                {goal.over ? (
                                    <p className="text-muted-foreground">
                                        That is{' '}
                                        {rupiah(goal.balance - goal.target)}{' '}
                                        past the target.
                                    </p>
                                ) : (
                                    <p className="text-muted-foreground">
                                        {rupiah(goal.remaining)} to go.
                                    </p>
                                )}
                                {goal.target_date && (
                                    <p className="text-muted-foreground">
                                        Deadline{' '}
                                        {new Date(
                                            goal.target_date,
                                        ).toLocaleDateString('en-GB', {
                                            day: 'numeric',
                                            month: 'short',
                                            year: 'numeric',
                                        })}
                                    </p>
                                )}
                            </div>

                            {goal.savings.length > 0 && (
                                <div className="rounded-xl border p-3 text-sm">
                                    <p className="mb-1 font-semibold">
                                        Saved by period
                                    </p>
                                    {goal.savings.map((saving) => (
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
                                                        {rupiah(saving.saved)}{' '}
                                                        saved
                                                    </span>
                                                )}
                                                {saving.withdrawn > 0 && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {rupiah(
                                                            saving.withdrawn,
                                                        )}{' '}
                                                        taken out
                                                    </span>
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <h2 className="text-base font-semibold">
                                Movements
                            </h2>

                            {goal.movements.length === 0 && (
                                <p className="py-6 text-center text-muted-foreground">
                                    No money saved yet.
                                </p>
                            )}

                            {goal.movements.map((movement) => (
                                <div
                                    key={movement.id}
                                    className="flex items-center gap-3 py-2"
                                >
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
                                            {new Date(
                                                movement.occurred_at,
                                            ).toLocaleDateString('en-GB', {
                                                day: 'numeric',
                                                month: 'short',
                                            })}
                                        </span>
                                    </span>
                                    <span
                                        className={cn(
                                            'font-semibold',
                                            movement.signed > 0 &&
                                                'text-green-700',
                                        )}
                                    >
                                        {movement.signed > 0 ? '+' : '-'}
                                        {rupiah(Math.abs(movement.signed))}
                                    </span>
                                    {movement.can_remove && editable && (
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            aria-label="Remove movement"
                                            onClick={() =>
                                                setPendingMovement(movement)
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>

                        <DrawerFooter className="py-6">
                            {editable ? (
                                <div className="grid gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIsEditOpen(true)}
                                    >
                                        <PencilIcon className="mr-1 size-4" />{' '}
                                        Edit
                                    </Button>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setIsContributionOpen(true)
                                            }
                                        >
                                            <PlusIcon className="mr-1 size-4" />{' '}
                                            Add money
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setIsWithdrawalOpen(true)
                                            }
                                        >
                                            <MinusIcon className="mr-1 size-4" />{' '}
                                            Take out
                                        </Button>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIsCompleteOpen(true)}
                                    >
                                        <CheckIcon className="mr-1 size-4" />{' '}
                                        Complete goal
                                    </Button>
                                </div>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Close
                                </Button>
                            )}
                        </DrawerFooter>
                    </div>

                    {/* NESTED DRAWERS: edit, contribution, withdrawal */}
                    <GoalDrawer
                        open={isEditOpen}
                        onOpenChange={setIsEditOpen}
                        householdId={householdId}
                        goal={goal}
                        sweepBudgets={goal.sweepBudgets}
                        sweepBudgetIds={goal.sweepBudgetIds}
                    />

                    <GoalContributionDrawer
                        open={isContributionOpen}
                        onOpenChange={setIsContributionOpen}
                        householdId={householdId}
                        goalId={goal.id}
                        budgets={budgets}
                        today={today}
                    />

                    <GoalWithdrawalDrawer
                        open={isWithdrawalOpen}
                        onOpenChange={setIsWithdrawalOpen}
                        householdId={householdId}
                        goalId={goal.id}
                        balance={goal.balance}
                        budgets={budgets}
                        today={today}
                    />
                </DrawerContent>
            </Drawer>

            <AlertDialog
                open={pendingMovement !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) setPendingMovement(null);
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
        </>
    );
}
