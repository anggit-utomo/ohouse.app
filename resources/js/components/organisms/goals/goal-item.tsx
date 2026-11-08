import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';
import { BudgetBar } from '@/components/budget-bar';
import { rupiah } from '@/lib/money';
import type { GoalSummary } from '@/types';

export default function GoalItem({
    goal,
    base,
}: {
    goal: GoalSummary;
    base: string;
}) {
    return (
        <Link href={`${base}/goals/${goal.id}`} className="block py-1">
            <div className="mb-2 flex items-center justify-between">
                <span className="flex items-center gap-0.5 font-semibold">
                    {goal.name}
                    <ChevronRightIcon className="size-4 text-muted-foreground" />
                </span>
                <span className="text-sm font-semibold">{goal.pct}%</span>
            </div>
            <BudgetBar pct={Math.min(goal.pct, 100)} over={goal.over} />
            <div className="mt-1 flex justify-between text-xs text-muted-foreground">
                <span>{rupiah(goal.balance)} saved</span>
                <span>of {rupiah(goal.target)}</span>
            </div>
        </Link>
    );
}
