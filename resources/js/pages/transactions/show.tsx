import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { PageHeader } from '@/components/page-header';
import { TransactionDrawer } from '@/components/organisms/transactions/transaction-drawer';
import { Defaults } from '@/types';

type Props = {
    household: { id: number };
    transaction: {
        id: number;
        type: string;
        amount: number;
        note: string | null;
        budget: string;
        user: string;
        occurred_at: string;
        left: number;
    };
    locked: boolean;
    isTransfer: boolean;
    canEdit: boolean;
    moved: { from: string; to: string } | null;
    transferId: string | null;
    budgets: { id: number; name: string; left: number }[];
    members: { id: number; name: string }[];
    defaults: Defaults;
    today: string;
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

export default function ShowTransaction({
    household,
    transaction: t,
    locked,
    isTransfer,
    canEdit,
    moved,
    transferId,
    budgets,
    members,
    defaults,
    today,
}: Props) {
    const base = `/h/${household.id}/transactions`;
    const plus = t.type === 'income';

    const [isEditDrawerOpen, setIsEditDrawerOpen] = useState(false);
    const [isUndoMoveAlertOpen, setIsUndoMoveAlertOpen] = useState(false);
    const [isDeleteAlertOpen, setIsDeleteAlertOpen] = useState(false);

    const handleConfirmUndoMove = () => {
        router.delete(`/h/${household.id}/transfers/${transferId}`, {
            onFinish: () => setIsUndoMoveAlertOpen(false),
        });
    };

    const handleConfirmDelete = () => {
        router.delete(`${base}/${t.id}`, {
            preserveScroll: true,
            replace: true,
            preserveState: true,
            onFinish: () => setIsDeleteAlertOpen(false),
        });
    };

    const { errors } = usePage().props as { errors: Record<string, string> };
    const problem = errors.transfer ?? errors.occurred_at ?? errors.type;

    return (
        <>
            <Head title="Transaction" />

            <PageHeader
                title="Transaction"
                href={`${base}?month=${t.occurred_at.slice(0, 7)}`}
            />

            <div className="py-4 text-center">
                <Badge variant="outline">{t.budget}</Badge>
                <p
                    className={`mt-2 text-4xl font-bold ${plus ? 'text-green-700' : ''}`}
                >
                    {moved ? '' : plus ? '+' : '-'}
                    {rupiah(t.amount)}
                </p>
                {moved ? (
                    <p className="text-lg font-semibold">
                        Moved {moved.from} → {moved.to}
                    </p>
                ) : (
                    t.note && <p className="text-muted-foreground">{t.note}</p>
                )}
            </div>

            <div className="rounded-xl border px-4">
                <Row first label="Budget source" value={t.budget} />
                <Row label={plus ? 'Received by' : 'Paid by'} value={t.user} />
                <Row
                    label="Date"
                    value={new Date(t.occurred_at).toLocaleString('en-GB', {
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
                <p className="mt-2 text-sm text-destructive">{problem}</p>
            )}

            {locked && (
                <p className="mt-2 text-sm text-muted-foreground">
                    This month is closed, so the entry is locked. Add a
                    correction in the current month instead.
                </p>
            )}
            {isTransfer && !locked && (
                <p className="mt-2 text-sm text-muted-foreground">
                    A move can't be edited. Undo it and move the right amount
                    instead.
                </p>
            )}

            {canEdit && (!isTransfer || !locked) && (
                <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                    <div className="mx-auto flex max-w-md gap-2">
                        {isTransfer ? (
                            <Button
                                variant="outline"
                                className="h-11 flex-1 text-destructive"
                                onClick={() => setIsUndoMoveAlertOpen(true)}
                            >
                                Undo move
                            </Button>
                        ) : (
                            <>
                                <Button
                                    variant="outline"
                                    className="h-11 flex-1"
                                    onClick={() => setIsEditDrawerOpen(true)}
                                >
                                    Edit
                                </Button>
                                <Button
                                    variant="outline"
                                    className="h-11 flex-1 text-destructive"
                                    onClick={() => setIsDeleteAlertOpen(true)}
                                >
                                    Delete
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            )}

            {/* Reusable Edit Drawer */}
            <TransactionDrawer
                open={isEditDrawerOpen}
                onOpenChange={setIsEditDrawerOpen}
                householdId={household.id}
                mode="edit"
                transactionId={t.id}
                defaults={defaults}
                budgets={budgets}
                members={members}
                today={today}
            />

            {/* Undo Move Confirmation Dialog */}
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

            {/* Delete Transaction Confirmation Dialog */}
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
