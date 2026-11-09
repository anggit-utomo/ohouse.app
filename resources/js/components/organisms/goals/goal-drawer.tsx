import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
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
import {
    Combobox,
    ComboboxClear,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
    ComboboxValue,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { currency, plain } from '@/lib/money';
import type { GoalDetail, SweepBudget } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    householdId: number;
    goal?: GoalDetail | null;
    sweepBudgets?: SweepBudget[];
    sweepBudgetIds?: number[];
};

type Data = {
    name: string;
    target_amount: string;
    target_date: string;
    sort: string;
    sweep_budget_ids: number[];
};

const empty: Data = {
    name: '',
    target_amount: '',
    target_date: '',
    sort: '',
    sweep_budget_ids: [],
};

const initialData = (
    goal: GoalDetail | null,
    sweepBudgetIds: number[],
): Data =>
    goal
        ? {
              name: goal.name,
              target_amount: String(goal.target),
              target_date: goal.target_date ?? '',
              sort: String(goal.sort),
              sweep_budget_ids: sweepBudgetIds,
          }
        : empty;

const noSweepBudgetIds: number[] = [];

export function GoalDrawer({
    open,
    onOpenChange,
    householdId,
    goal = null,
    sweepBudgets = [],
    sweepBudgetIds = noSweepBudgetIds,
}: Props) {
    const form = useForm<Data>(initialData(goal, sweepBudgetIds));
    const [isAlertOpen, setIsAlertOpen] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData(initialData(goal, sweepBudgetIds));
        form.clearErrors();
    }, [open, goal, sweepBudgetIds]);

    const forceClose = () => {
        setIsAlertOpen(false);
        onOpenChange(false);
        form.reset();
        form.clearErrors();
    };

    const handleAttemptClose = () => {
        if (form.isDirty) {
            setIsAlertOpen(true);
        } else {
            forceClose();
        }
    };

    const base = `/h/${householdId}`;
    const errors = form.errors as Record<string, string>;
    const error =
        errors.name ??
        errors.target_amount ??
        errors.target_date ??
        errors.sort ??
        errors.sweep_budget_ids;

    const selectedSweepBudgets = sweepBudgets.filter((budget) =>
        form.data.sweep_budget_ids.includes(budget.id),
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((d) => ({
            name: d.name,
            target_amount: Number(d.target_amount || 0),
            target_date: d.target_date || null,
            sort: d.sort === '' ? null : Number(d.sort),
            sweep_budget_ids: d.sweep_budget_ids,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => forceClose(),
        };

        if (goal) {
            form.patch(`${base}/goals/${goal.id}`, options);
        } else {
            form.post(`${base}/goals`, options);
        }
    };

    return (
        <>
            <Drawer
                open={open}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        handleAttemptClose();
                    } else {
                        onOpenChange(true);
                    }
                }}
            >
                <DrawerContent>
                    <div className="mx-auto w-full max-w-sm">
                        <form onSubmit={submit}>
                            <div className="space-y-4">
                                <DrawerHeader className="px-0">
                                    <DrawerTitle>
                                        {goal ? 'Edit goal' : 'New goal'}
                                    </DrawerTitle>
                                    <DrawerDescription>
                                        Set aside money for something worth
                                        saving for.
                                    </DrawerDescription>
                                </DrawerHeader>

                                <div className="grid gap-3 px-4">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="goal-name">Name</Label>
                                        <Input
                                            id="goal-name"
                                            placeholder="Bali trip"
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="py-2 text-center">
                                        <Label
                                            htmlFor="goal-target"
                                            className="text-muted-foreground"
                                        >
                                            Target
                                        </Label>
                                        <div className="flex items-baseline justify-center gap-2">
                                            <span className="text-xl text-muted-foreground">
                                                {currency().symbol}
                                            </span>
                                            <input
                                                id="goal-target"
                                                inputMode="numeric"
                                                placeholder="0"
                                                className="w-48 bg-transparent text-center text-4xl font-bold outline-none"
                                                value={
                                                    form.data.target_amount
                                                        ? plain(
                                                              Number(
                                                                  form.data
                                                                      .target_amount,
                                                              ),
                                                          )
                                                        : ''
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'target_amount',
                                                        e.target.value.replace(
                                                            /\D/g,
                                                            '',
                                                        ),
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="goal-date">
                                            Deadline
                                        </Label>
                                        <Input
                                            id="goal-date"
                                            type="date"
                                            value={form.data.target_date}
                                            onChange={(e) =>
                                                form.setData(
                                                    'target_date',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="goal-sort">Order</Label>
                                        <Input
                                            id="goal-sort"
                                            inputMode="numeric"
                                            placeholder="Next"
                                            value={form.data.sort}
                                            onChange={(e) =>
                                                form.setData(
                                                    'sort',
                                                    e.target.value.replace(
                                                        /\D/g,
                                                        '',
                                                    ),
                                                )
                                            }
                                        />
                                    </div>

                                    {sweepBudgets.length > 0 && (
                                        <div className="grid gap-1.5">
                                            <Label>Sweep leftovers from</Label>
                                            <p className="text-xs text-muted-foreground">
                                                At close, a selected
                                                budget&apos;s leftover moves
                                                into this goal. A sweep cannot
                                                be undone.
                                            </p>
                                            <Combobox
                                                items={sweepBudgets}
                                                multiple
                                                autoHighlight
                                                itemToStringLabel={(budget) =>
                                                    budget.name
                                                }
                                                value={selectedSweepBudgets}
                                                onValueChange={(budgets) =>
                                                    form.setData(
                                                        'sweep_budget_ids',
                                                        budgets.map(
                                                            (budget) =>
                                                                budget.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <div className="flex items-center gap-1">
                                                    <ComboboxTrigger
                                                        type="button"
                                                        render={
                                                            <Button
                                                                variant="outline"
                                                                className="min-w-0 flex-1 justify-between font-normal"
                                                            />
                                                        }
                                                    >
                                                        <ComboboxValue>
                                                            {(
                                                                budgets: SweepBudget[],
                                                            ) =>
                                                                budgets.length ===
                                                                0
                                                                    ? 'Select budgets...'
                                                                    : `${budgets.length} selected`
                                                            }
                                                        </ComboboxValue>
                                                    </ComboboxTrigger>
                                                    {selectedSweepBudgets.length >
                                                        0 && (
                                                        <ComboboxClear
                                                            type="button"
                                                            aria-label="Clear sweep budgets"
                                                            render={
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon-sm"
                                                                />
                                                            }
                                                        />
                                                    )}
                                                </div>
                                                <ComboboxContent>
                                                    <ComboboxInput
                                                        showTrigger={false}
                                                        placeholder="Search budgets..."
                                                    />
                                                    <ComboboxEmpty>
                                                        No budgets found.
                                                    </ComboboxEmpty>
                                                    <ComboboxList>
                                                        {(budget) => (
                                                            <ComboboxItem
                                                                key={budget.id}
                                                                value={budget}
                                                            >
                                                                {budget.name}
                                                            </ComboboxItem>
                                                        )}
                                                    </ComboboxList>
                                                </ComboboxContent>
                                            </Combobox>
                                        </div>
                                    )}

                                    {error && (
                                        <p className="text-sm text-destructive">
                                            {error}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <DrawerFooter className="py-6">
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            !form.data.name.trim() ||
                                            !form.data.target_amount
                                        }
                                    >
                                        {form.processing ? (
                                            <>
                                                <Spinner /> Saving...
                                            </>
                                        ) : (
                                            'Save goal'
                                        )}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleAttemptClose}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </DrawerFooter>
                        </form>
                    </div>
                </DrawerContent>
            </Drawer>

            <AlertDialog open={isAlertOpen} onOpenChange={setIsAlertOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Discard changes?</AlertDialogTitle>
                        <AlertDialogDescription>
                            You have unsaved form inputs. Leaving now will reset
                            and lose your progress.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Continue Editing</AlertDialogCancel>
                        <AlertDialogAction onClick={forceClose}>
                            Discard
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
