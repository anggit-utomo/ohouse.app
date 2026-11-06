import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { rupiah } from '@/lib/money';
import { TransactionDrawer } from './transaction-drawer';
import { Defaults, TransactionType } from '@/types';

export type TransactionDetail = {
    id: number;
    type: TransactionType;
    amount: number;
    signed: number;
    note: string | null;
    budget: string;
    budget_id?: number;
    user: string;
    user_id?: number;
    occurred_at: string;
    left: number;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    householdId: number;
    transaction: TransactionDetail | null;
    locked?: boolean;
    isTransfer?: boolean;
    canEdit?: boolean;
    moved?: { from: string; to: string } | null;
    transferId?: string | null;
    budgets?: { id: number; name: string; left: number }[];
    members?: { id: number; name: string }[];
    defaults?: Defaults;
    today?: string;
};

function Row({
    label,
    value,
    first,
}: {
    label: string;
    value: string;
    first?: boolean;
}) {
    return (
        <div className={`flex justify-between py-3 ${first ? '' : 'border-t'}`}>
            <span className="text-muted-foreground">{label}</span>
            <span className="font-semibold">{value}</span>
        </div>
    );
}

export function TransactionDetailDrawer({
    open,
    onOpenChange,
    householdId,
    transaction: t,
    locked = false,
    isTransfer = false,
    canEdit = true,
    moved = null,
    transferId = null,
    budgets = [],
    members = [],
    defaults,
    today = new Date().toISOString().split('T')[0],
}: Props) {
    const [isUndoMoveAlertOpen, setIsUndoMoveAlertOpen] = useState(false);
    const [isDeleteAlertOpen, setIsDeleteAlertOpen] = useState(false);
    const [isEditDrawerOpen, setIsEditDrawerOpen] = useState(false);

    if (!t) return null;

    const base = `/h/${householdId}/transactions`;
    const plus = t.signed > 0;

    const { errors } = usePage().props as { errors?: Record<string, string> };
    const problem = errors?.transfer ?? errors?.occurred_at ?? errors?.type;

    // Prefill edit form defaults from current transaction data
    const currentBudget = t.budget_id
        ? budgets.find((b) => b.id === Number(t.budget_id))
        : budgets.find((b) => b.name === t.budget);

    const currentMember = t.user_id
        ? members.find((m) => m.id === Number(t.user_id))
        : members.find((m) => m.name === t.user);

    // Build editDefaults cleanly as string values
    const editDefaults: Defaults = {
        type: t.type === 'income' ? 'income' : 'expense',
        amount: String(t.amount),
        budget_id: currentBudget
            ? String(currentBudget.id)
            : defaults?.budget_id
              ? String(defaults.budget_id)
              : '',
        note: t.note ?? '',
        occurred_at: t.occurred_at ? t.occurred_at.slice(0, 10) : today,
        user_id: currentMember
            ? String(currentMember.id)
            : defaults?.user_id
              ? String(defaults.user_id)
              : '',
        corrects_transaction_id: null,
    };

    const handleConfirmUndoMove = () => {
        if (!transferId) return;
        router.delete(`/h/${householdId}/transfers/${transferId}`, {
            onSuccess: () => {
                setIsUndoMoveAlertOpen(false);
                onOpenChange(false);
            },
        });
    };

    const handleConfirmDelete = () => {
        router.delete(`${base}/${t.id}`, {
            preserveScroll: true,
            replace: true,
            preserveState: true,
            onSuccess: () => {
                setIsDeleteAlertOpen(false);
                onOpenChange(false);
            },
        });
    };

    return (
        <>
            <Drawer open={open} onOpenChange={onOpenChange}>
                <DrawerContent>
                    <div className="mx-auto w-full max-w-sm">
                        <DrawerHeader>
                            <DrawerTitle className="text-center">
                                Transaction Details
                            </DrawerTitle>
                            <DrawerDescription className="text-center">
                                View record summary and actions
                            </DrawerDescription>
                        </DrawerHeader>

                        <div className="space-y-4 px-4">
                            <div className="py-2 text-center">
                                <Badge variant="outline">{t.budget}</Badge>
                                <p
                                    className={`mt-2 text-4xl font-bold ${
                                        plus ? 'text-green-700' : ''
                                    }`}
                                >
                                    {moved ? '' : plus ? '+' : '-'}
                                    {rupiah(t.amount)}
                                </p>
                                {moved ? (
                                    <p className="text-base font-semibold">
                                        Moved {moved.from} → {moved.to}
                                    </p>
                                ) : (
                                    t.note && (
                                        <p className="text-muted-foreground">
                                            {t.note}
                                        </p>
                                    )
                                )}
                            </div>

                            <div className="rounded-xl border px-4">
                                <Row
                                    first
                                    label="Budget source"
                                    value={t.budget}
                                />
                                <Row
                                    label={plus ? 'Received by' : 'Paid by'}
                                    value={t.user}
                                />
                                <Row
                                    label="Date"
                                    value={new Date(
                                        t.occurred_at,
                                    ).toLocaleString('en-GB', {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })}
                                />
                                <Row
                                    label={`Left in ${t.budget}`}
                                    value={
                                        t.left < 0
                                            ? `Over by ${rupiah(-t.left)}`
                                            : rupiah(t.left)
                                    }
                                />
                            </div>

                            {problem && (
                                <p className="text-sm text-destructive">
                                    {problem}
                                </p>
                            )}

                            {locked && (
                                <p className="text-xs text-muted-foreground">
                                    This month is closed, so the entry is
                                    locked.
                                </p>
                            )}
                            {isTransfer && !locked && (
                                <p className="text-xs text-muted-foreground">
                                    A move can't be edited. Undo it and move the
                                    right amount instead.
                                </p>
                            )}
                        </div>

                        <DrawerFooter className="mt-4 pb-6">
                            {canEdit && !locked ? (
                                <div className="flex gap-2">
                                    {isTransfer ? (
                                        <Button
                                            variant="outline"
                                            className="h-11 flex-1 text-destructive"
                                            onClick={() =>
                                                setIsUndoMoveAlertOpen(true)
                                            }
                                        >
                                            Undo move
                                        </Button>
                                    ) : (
                                        <>
                                            <Button
                                                variant="outline"
                                                className="h-11 flex-1"
                                                onClick={() =>
                                                    setIsEditDrawerOpen(true)
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="outline"
                                                className="h-11 flex-1 text-destructive"
                                                onClick={() =>
                                                    setIsDeleteAlertOpen(true)
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </>
                                    )}
                                </div>
                            ) : (
                                <Button
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Close
                                </Button>
                            )}
                        </DrawerFooter>
                    </div>

                    {/* NESTED DRAWER: Edit Transaction Form */}
                    <TransactionDrawer
                        open={isEditDrawerOpen}
                        onOpenChange={setIsEditDrawerOpen}
                        householdId={householdId}
                        mode="edit"
                        transactionId={t.id}
                        defaults={editDefaults}
                        budgets={budgets}
                        members={members}
                        today={today}
                        onSuccess={() => {
                            setIsEditDrawerOpen(false);
                            onOpenChange(false);
                        }}
                    />
                </DrawerContent>
            </Drawer>

            {/* Undo Move Alert Dialog */}
            <AlertDialog
                open={isUndoMoveAlertOpen}
                onOpenChange={setIsUndoMoveAlertOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Undo this move?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Both budget balances will go back to how they were
                            before the transfer.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmUndoMove}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            Undo Move
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Delete Transaction Alert Dialog */}
            <AlertDialog
                open={isDeleteAlertOpen}
                onOpenChange={setIsDeleteAlertOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete transaction?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently remove the record and update
                            your budget balance.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmDelete}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
