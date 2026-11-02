import { BudgetBar } from '@/components/budget-bar';
import { rupiah } from '@/lib/money';
import { Budget } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';

interface BudgetItemProps {
    b: Budget;
    base: string;
    canEdit: boolean;
}

export default function BudgetItem({ b, base, canEdit }: BudgetItemProps) {
    const leftText = (left: number) =>
        left < 0 ? `Over by ${rupiah(-left)}` : `${rupiah(left)} left`;

    return (
        <div className="py-1">
            <Link href={`${base}/budgets/${b.id}`} className="block">
                <div className="mb-2 flex items-center justify-between">
                    <span className="flex items-center gap-0.5 font-semibold">
                        {b.name}
                        <ChevronRightIcon className="size-4 text-muted-foreground" />
                    </span>
                    <span
                        className={`text-sm ${b.over ? 'text-destructive' : 'text-muted-foreground'}`}
                    >
                        {leftText(b.left)}
                    </span>
                </div>
                <BudgetBar pct={b.pct} over={b.over} />
            </Link>
            {b.over && canEdit && (
                <div className="mt-2 flex justify-between text-xs">
                    <span className="text-muted-foreground">
                        Cover it from another budget
                    </span>
                    <Link
                        href={`${base}/transfers/create?to=${b.id}`}
                        className="font-semibold"
                    >
                        Move money
                    </Link>
                </div>
            )}
        </div>
    );
}
