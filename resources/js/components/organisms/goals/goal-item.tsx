import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';
import { BudgetBar } from '@/components/budget-bar';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { rupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { GoalSummary } from '@/types';

type GoalItemProps = {
    goal: GoalSummary;
    base: string;
    onSelect?: (goal: GoalSummary) => void;
    card?: boolean;
    single?: boolean;
};

export default function GoalItem({
    goal,
    base,
    onSelect,
    card = false,
    single = false,
}: GoalItemProps) {
    const body = (
        <>
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
        </>
    );

    // Card variant for Home horizontal scroll — same width logic as BudgetItem
    if (card) {
        const cardClass = cn(
            'h-full',
            single ? 'w-full' : 'w-54 flex-shrink-0',
        );

        const inner = (
            <>
                <CardHeader>
                    <CardTitle className="flex min-w-0 items-center gap-0.5 text-base">
                        <span className="truncate">{goal.name}</span>
                        <ChevronRightIcon className="size-4 shrink-0 text-muted-foreground" />
                    </CardTitle>
                    <CardAction className="shrink-0 text-sm font-semibold whitespace-nowrap">
                        {goal.pct}%
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <BudgetBar pct={Math.min(goal.pct, 100)} over={goal.over} />
                    <div className="mt-1 flex justify-between text-xs text-muted-foreground">
                        <span>{rupiah(goal.balance)} saved</span>
                        <span>of {rupiah(goal.target)}</span>
                    </div>
                </CardContent>
            </>
        );

        if (onSelect) {
            return (
                <Card className={cardClass}>
                    <button
                        type="button"
                        className="flex flex-1 flex-col gap-(--card-spacing) text-left"
                        onClick={() => onSelect(goal)}
                    >
                        {inner}
                    </button>
                </Card>
            );
        }

        return (
            <Card className={cardClass}>
                <Link
                    href={`${base}/goals`}
                    className="flex flex-1 flex-col gap-(--card-spacing)"
                >
                    {inner}
                </Link>
            </Card>
        );
    }

    if (onSelect) {
        return (
            <button
                type="button"
                className="block w-full py-1 text-left"
                onClick={() => onSelect(goal)}
            >
                {body}
            </button>
        );
    }

    return (
        <Link href={`${base}/goals`} className="block py-1">
            {body}
        </Link>
    );
}
