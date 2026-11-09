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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { currency, plain, rupiah } from '@/lib/money';
import { setIfNotNull } from '@/lib/utils';
import type { BudgetOption } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    householdId: number;
    goalId: number;
    budgets: BudgetOption[];
    today: string;
};

type Data = {
    budget_id: string;
    amount: string;
    note: string;
    occurred_at: string;
};

const OUTSIDE = 'outside';

const initialData = (today: string): Data => ({
    budget_id: OUTSIDE,
    amount: '',
    note: '',
    occurred_at: today,
});

export function GoalContributionDrawer({
    open,
    onOpenChange,
    householdId,
    goalId,
    budgets,
    today,
}: Props) {
    const base = `/h/${householdId}`;
    const form = useForm<Data>(initialData(today));
    const [isAlertOpen, setIsAlertOpen] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const data = initialData(today);

        form.setDefaults(data);
        form.setData(data);
        form.clearErrors();
    }, [open, today]);

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

    const errors = form.errors as Record<string, string>;
    const error =
        errors.amount ??
        errors.note ??
        errors.occurred_at ??
        errors.goal ??
        errors.budget_id;

    const source = budgets.find(
        (budget) => String(budget.id) === form.data.budget_id,
    );
    const amount = Number(form.data.amount || 0);
    const tooMuch = !!source && amount > Math.max(source.left, 0);
    const leftText = (left: number) =>
        left < 0 ? `over by ${rupiah(-left)}` : `${rupiah(left)} left`;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((d) => ({
            type: 'contribution',
            budget_id: d.budget_id === OUTSIDE ? null : Number(d.budget_id),
            amount: Number(d.amount || 0),
            note: d.note || null,
            occurred_at: d.occurred_at || null,
        }));

        form.post(`${base}/goals/${goalId}/movements`, {
            preserveScroll: true,
            onSuccess: () => forceClose(),
        });
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
                                    <DrawerTitle>Add money</DrawerTitle>
                                    <DrawerDescription>
                                        From a budget this month, or from
                                        outside the monthly budgets.
                                    </DrawerDescription>
                                </DrawerHeader>

                                <div className="grid gap-3 px-4">
                                    <div className="grid gap-1.5">
                                        <Label>From</Label>
                                        <Select
                                            value={form.data.budget_id}
                                            onValueChange={setIfNotNull(
                                                (value) =>
                                                    form.setData(
                                                        'budget_id',
                                                        value,
                                                    ),
                                            )}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Outside the budget">
                                                    {source ? (
                                                        <span>
                                                            {source.name} ·{' '}
                                                            {leftText(
                                                                source.left,
                                                            )}
                                                        </span>
                                                    ) : form.data.budget_id ===
                                                      OUTSIDE ? (
                                                        <span>
                                                            Outside the budget
                                                        </span>
                                                    ) : null}
                                                </SelectValue>
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={OUTSIDE}>
                                                    Outside the budget
                                                </SelectItem>
                                                {budgets.map((budget) => (
                                                    <SelectItem
                                                        key={budget.id}
                                                        value={String(
                                                            budget.id,
                                                        )}
                                                    >
                                                        {budget.name} ·{' '}
                                                        {leftText(budget.left)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="py-2 text-center">
                                        <Label
                                            htmlFor="contribution-amount"
                                            className="text-muted-foreground"
                                        >
                                            Amount
                                        </Label>
                                        <div className="flex items-baseline justify-center gap-2">
                                            <span className="text-xl text-muted-foreground">
                                                {currency().symbol}
                                            </span>
                                            <input
                                                id="contribution-amount"
                                                inputMode="numeric"
                                                placeholder="0"
                                                className="w-48 bg-transparent text-center text-4xl font-bold outline-none"
                                                value={
                                                    form.data.amount
                                                        ? plain(
                                                              Number(
                                                                  form.data
                                                                      .amount,
                                                              ),
                                                          )
                                                        : ''
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'amount',
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
                                        <Label htmlFor="contribution-note">
                                            Note
                                        </Label>
                                        <Input
                                            id="contribution-note"
                                            placeholder="Optional"
                                            value={form.data.note}
                                            onChange={(e) =>
                                                form.setData(
                                                    'note',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="contribution-date">
                                            Date
                                        </Label>
                                        <Input
                                            id="contribution-date"
                                            type="date"
                                            max={today}
                                            value={form.data.occurred_at}
                                            onChange={(e) =>
                                                form.setData(
                                                    'occurred_at',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    {(error || tooMuch) && (
                                        <p className="text-sm text-destructive">
                                            {error ??
                                                `${source?.name} only has ${leftText(
                                                    Math.max(
                                                        source?.left ?? 0,
                                                        0,
                                                    ),
                                                )}.`}
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
                                            !form.data.amount ||
                                            tooMuch
                                        }
                                    >
                                        {form.processing ? (
                                            <>
                                                <Spinner /> Adding...
                                            </>
                                        ) : (
                                            'Add money'
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
