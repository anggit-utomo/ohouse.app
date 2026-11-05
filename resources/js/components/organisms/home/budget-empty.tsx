import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';

interface BudgetEmptyProps {
    base: string;
    canEdit: boolean;
}

export default function BudgetEmpty({ base, canEdit }: BudgetEmptyProps) {
    return (
        <div className="rounded-xl border p-6 text-center">
            <p className="font-semibold">No budgets yet</p>
            <p className="mb-4 text-sm text-muted-foreground">
                Set an amount for food, fuel and the rest to start tracking.
            </p>
            {canEdit && (
                <Button render={<Link href={`${base}/budgets`} />}>
                    Set budgets
                </Button>
            )}
        </div>
    );
}
