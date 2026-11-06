import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
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
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { TransactionFormFields } from './transaction-form-fields';
import type { BudgetOption, MemberOption, TransactionDefaults } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    householdId: number;
    mode?: 'create' | 'edit';
    transactionId?: number;
    defaults: TransactionDefaults;
    budgets: BudgetOption[];
    members: MemberOption[];
    today: string;
    onSuccess?: () => void;
};

export function TransactionDrawer({
    open,
    onOpenChange,
    householdId,
    mode = 'create',
    transactionId,
    defaults,
    budgets,
    members,
    today,
    onSuccess,
}: Props) {
    const form = useForm<TransactionDefaults>(defaults);
    const [isAlertOpen, setIsAlertOpen] = useState(false);

    useEffect(() => {
        if (open) {
            form.setData({
                ...defaults,
                budget_id: defaults.budget_id ? String(defaults.budget_id) : '',
                user_id: defaults.user_id ? String(defaults.user_id) : '',
                amount: String(defaults.amount ?? ''),
            });
            form.clearErrors();
        }
    }, [open, defaults]);

    const forceClose = () => {
        setIsAlertOpen(false);
        onOpenChange(false);
        form.reset();
        form.clearErrors();
        onSuccess?.();
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
            amount: Number(d.amount || 0),
            budget_id: Number(d.budget_id),
            user_id: Number(d.user_id),
            corrects_transaction_id: d.corrects_transaction_id
                ? Number(d.corrects_transaction_id)
                : null,
        }));

        const base = `/h/${householdId}/transactions`;

        if (mode === 'edit' && transactionId) {
            form.put(`${base}/${transactionId}`, {
                replace: true,
                onSuccess: () => forceClose(),
            });
        } else {
            form.post(base, {
                replace: true,
                onSuccess: () => forceClose(),
            });
        }
    };

    const isIncome = form.data.type === 'income';

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
                            <div>
                                <DrawerHeader>
                                    <DrawerTitle>
                                        {mode === 'edit'
                                            ? 'Edit Transaction'
                                            : 'New Transaction'}
                                    </DrawerTitle>
                                    <DrawerDescription>
                                        {mode === 'edit'
                                            ? 'Update details of your transaction.'
                                            : 'Enter details to record a new transaction.'}
                                    </DrawerDescription>
                                </DrawerHeader>

                                <div className="px-4">
                                    <TransactionFormFields
                                        form={form}
                                        budgets={budgets}
                                        members={members}
                                        today={today}
                                        showAfterBalance={mode === 'create'}
                                    />
                                </div>
                            </div>

                            <DrawerFooter className="mt-4 pb-6">
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing ? (
                                            <>
                                                <Spinner /> Saving
                                            </>
                                        ) : mode === 'edit' ? (
                                            'Save changes'
                                        ) : isIncome ? (
                                            'Save income'
                                        ) : (
                                            'Save expense'
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
