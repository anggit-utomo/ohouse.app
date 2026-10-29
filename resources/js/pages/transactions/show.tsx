import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/money';

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
};

export default function ShowTransaction({
    household,
    transaction: t,
    locked,
    isTransfer,
    canEdit,
    moved,
    transferId,
}: Props) {
    const base = `/h/${household.id}/transactions`;
    const plus = t.type === 'income';
    const removeMove = () => {
        if (confirm('Undo this move? Both budgets go back to how they were.'))
            router.delete(`/h/${household.id}/transfers/${transferId}`);
    };

    const remove = () => {
        if (confirm('Delete this transaction? The budget balance will update.'))
            router.delete(`${base}/${t.id}`);
    };

    const Row = ({
        label,
        value,
        first,
    }: {
        label: string;
        value: string;
        first?: boolean;
    }) => (
        <div className={`flex justify-between py-3 ${first ? '' : 'border-t'}`}>
            <span className="text-muted-foreground">{label}</span>
            <span className="font-semibold">{value}</span>
        </div>
    );

    return (
        <>
            <Head title="Transaction" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-28">
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
                        t.note && (
                            <p className="text-muted-foreground">{t.note}</p>
                        )
                    )}
                </div>

                <div className="rounded-xl border px-4">
                    <Row first label="Budget source" value={t.budget} />
                    <Row
                        label={plus ? 'Received by' : 'Paid by'}
                        value={t.user}
                    />
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

                {locked && (
                    <p className="text-sm text-muted-foreground">
                        This month is closed, so the entry is locked. Add a
                        correction in the current month instead.
                    </p>
                )}
                {isTransfer && !locked && (
                    <p className="text-sm text-muted-foreground">
                        A move can't be edited. Undo it and move the right
                        amount instead.
                    </p>
                )}
            </div>

            {canEdit && !isTransfer && (
                <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-6">
                    <div className="mx-auto flex max-w-md gap-2">
                        {isTransfer ? (
                            <Button
                                variant="outline"
                                className="h-11 flex-1 text-destructive"
                                onClick={removeMove}
                            >
                                Undo move
                            </Button>
                        ) : locked ? (
                            <Button className="h-11 flex-1" asChild>
                                <Link href={`${base}/create?corrects=${t.id}`}>
                                    Add correction
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button
                                    variant="outline"
                                    className="h-11 flex-1"
                                    asChild
                                >
                                    <Link href={`${base}/${t.id}/edit`}>
                                        Edit
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    className="h-11 flex-1 text-destructive"
                                    onClick={remove}
                                >
                                    Delete
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
