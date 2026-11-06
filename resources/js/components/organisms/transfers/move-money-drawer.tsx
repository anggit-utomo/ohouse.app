import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ArrowUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
import { currency, plain, rupiah } from '@/lib/money';
import { setIfNotNull } from '@/lib/utils';
import type { BudgetOption, TransferDefaults } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    householdId: number;
    budgets: BudgetOption[];
    defaults?: TransferDefaults;
};

const leftText = (n: number) =>
    n < 0 ? `over by ${rupiah(-n)}` : `${rupiah(n)} left`;

export function MoveMoneyDrawer({
    open,
    onOpenChange,
    householdId,
    budgets,
    defaults = { from_budget_id: '', to_budget_id: '', amount: '', note: '' },
}: Props) {
    const base = `/h/${householdId}`;
    const form = useForm<TransferDefaults>(defaults);
    const [isAlertOpen, setIsAlertOpen] = useState(false);

    const find = (id: string) => budgets.find((b) => String(b.id) === id);
    const from = find(form.data.from_budget_id);
    const to = find(form.data.to_budget_id);
    const amount = Number(form.data.amount || 0);
    const tooMuch = !!from && amount > Math.max(from.left, 0);
    const ready = !!from && !!to && amount > 0 && !tooMuch;
    const errors = form.errors as Record<string, string>;
    const error =
        errors.amount ??
        errors.from_budget_id ??
        errors.to_budget_id ??
        errors.occurred_at;

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

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((d) => ({
            ...d,
            amount: Number(d.amount),
            from_budget_id: Number(d.from_budget_id),
            to_budget_id: Number(d.to_budget_id),
        }));

        form.post(`${base}/transfers`, {
            replace: true,
            onSuccess: () => forceClose(),
        });
    };

    const picker = (
        label: string,
        key: 'from_budget_id' | 'to_budget_id',
        other: string,
    ) => {
        const isFrom = key === 'from_budget_id';
        const budget = isFrom ? from : to;
        const afterBalance = budget
            ? isFrom
                ? budget.left - amount
                : budget.left + amount
            : null;

        return (
            <div className="grid gap-1.5">
                <Label>{label}</Label>
                <Select
                    value={form.data[key]}
                    onValueChange={setIfNotNull((v) => form.setData(key, v))}
                >
                    <SelectTrigger className="w-full">
                        <SelectValue placeholder="Choose a budget" />
                    </SelectTrigger>
                    <SelectContent>
                        {budgets
                            .filter((b) => String(b.id) !== other)
                            .map((b) => (
                                <SelectItem key={b.id} value={String(b.id)}>
                                    {b.name} · {leftText(b.left)}
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>

                {budget && amount > 0 && afterBalance !== null && (
                    <p className="text-xs text-muted-foreground">
                        After:{' '}
                        <span
                            className={
                                (isFrom && tooMuch) || afterBalance < 0
                                    ? 'font-medium text-destructive'
                                    : 'font-medium text-foreground'
                            }
                        >
                            {leftText(afterBalance)}
                        </span>
                    </p>
                )}
            </div>
        );
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
                                    <DrawerTitle>Move Money</DrawerTitle>
                                    <DrawerDescription>
                                        Cover one budget with what is left in
                                        another.
                                    </DrawerDescription>
                                </DrawerHeader>

                                <div className="px-4">
                                    {picker(
                                        'From',
                                        'from_budget_id',
                                        form.data.to_budget_id,
                                    )}

                                    <div className="flex justify-center">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Swap budgets"
                                            onClick={() =>
                                                form.setData((d) => ({
                                                    ...d,
                                                    from_budget_id:
                                                        d.to_budget_id,
                                                    to_budget_id:
                                                        d.from_budget_id,
                                                }))
                                            }
                                        >
                                            <ArrowUpDown className="size-5" />
                                        </Button>
                                    </div>

                                    {picker(
                                        'To',
                                        'to_budget_id',
                                        form.data.from_budget_id,
                                    )}

                                    <div className="py-2 text-center">
                                        <Label
                                            htmlFor="transfer-amount"
                                            className="text-muted-foreground"
                                        >
                                            Amount
                                        </Label>
                                        <div className="flex items-baseline justify-center gap-2">
                                            <span className="text-xl text-muted-foreground">
                                                {currency().symbol}
                                            </span>
                                            <input
                                                id="transfer-amount"
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

                                    {(tooMuch || error) && (
                                        <p className="text-sm text-destructive">
                                            {error ??
                                                `${from?.name} only has ${rupiah(Math.max(from?.left ?? 0, 0))} left.`}
                                        </p>
                                    )}

                                    <div className="grid gap-2 pb-2">
                                        <Label htmlFor="transfer-note">
                                            Note
                                        </Label>
                                        <Input
                                            id="transfer-note"
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
                                </div>
                            </div>

                            <DrawerFooter className="pb-6">
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        type="submit"
                                        disabled={!ready || form.processing}
                                    >
                                        {form.processing ? (
                                            <>
                                                <Spinner /> Moving...
                                            </>
                                        ) : (
                                            'Move money'
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
