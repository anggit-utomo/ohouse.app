import { useState } from 'react';
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
import { Defaults } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    householdId: number;
    defaults: Defaults;
    budgets: { id: number; name: string; left: number }[];
    members: { id: number; name: string }[];
    today: string;
};

export function TransactionDrawer({
    open,
    onOpenChange,
    householdId,
    defaults,
    budgets,
    members,
    today,
}: Props) {
    const form = useForm<Defaults>(defaults);
    const [isAlertOpen, setIsAlertOpen] = useState(false);

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
            amount: Number(d.amount || 0),
            budget_id: Number(d.budget_id),
            user_id: Number(d.user_id),
            corrects_transaction_id: d.corrects_transaction_id
                ? Number(d.corrects_transaction_id)
                : null,
        }));

        form.post(`/h/${householdId}/transactions`, {
            replace: true,
            onSuccess: () => forceClose(),
        });
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
                            <DrawerHeader>
                                <DrawerTitle>New Transaction</DrawerTitle>
                                <DrawerDescription>
                                    Enter details to record a new transaction.
                                </DrawerDescription>
                            </DrawerHeader>

                            <div className="px-4">
                                <TransactionFormFields
                                    form={form}
                                    budgets={budgets}
                                    members={members}
                                    today={today}
                                />
                            </div>

                            <DrawerFooter className="mt-4">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? (
                                        <div className="flex items-center gap-2">
                                            <Spinner /> Saving
                                        </div>
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
                            </DrawerFooter>
                        </form>
                    </div>
                </DrawerContent>
            </Drawer>

            {/* Dirty Form Confirmation Dialog */}
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
