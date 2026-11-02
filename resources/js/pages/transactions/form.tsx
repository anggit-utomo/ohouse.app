import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/page-header';
import { TransactionFormFields } from '@/components/organisms/transactions/transaction-form-fields';
import { Defaults } from '@/types';

type Props = {
    household: { id: number };
    mode: 'create' | 'edit';
    transactionId?: number;
    budgets: { id: number; name: string; left: number }[];
    members: { id: number; name: string }[];
    defaults: Defaults;
    today: string;
};

export default function TransactionForm({
    household,
    mode,
    transactionId,
    budgets,
    members,
    defaults,
    today,
}: Props) {
    const base = `/h/${household.id}/transactions`;
    const form = useForm<Defaults>(defaults);
    const isIncome = form.data.type === 'income';

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

        if (mode === 'edit') {
            form.put(`${base}/${transactionId}`, { replace: true });
        } else {
            form.post(base, { replace: true });
        }
    };

    return (
        <form onSubmit={submit} className="pb-24">
            <Head
                title={mode === 'edit' ? 'Edit transaction' : 'New transaction'}
            />

            <PageHeader
                title={mode === 'edit' ? 'Edit transaction' : 'New transaction'}
                href={mode === 'edit' ? `${base}/${transactionId}` : base}
            />

            <TransactionFormFields
                form={form}
                budgets={budgets}
                members={members}
                today={today}
                showAfterBalance={mode === 'create'}
            />

            <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                <div className="mx-auto flex max-w-md gap-2">
                    {mode === 'edit' && (
                        <Button variant="outline" className="h-11" asChild>
                            <Link href={`${base}/${transactionId}`}>
                                Cancel
                            </Link>
                        </Button>
                    )}
                    <Button
                        type="submit"
                        className="h-11 flex-1"
                        disabled={form.processing}
                    >
                        {form.processing
                            ? 'Saving…'
                            : mode === 'edit'
                              ? 'Save changes'
                              : isIncome
                                ? 'Save income'
                                : 'Save expense'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
