import { BudgetBar } from '@/components/budget-bar';
import { MovedToGoals, ReturnedFromGoals } from '@/components/moved-to-goals';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { rupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { BudgetSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';

interface BudgetItemProps {
    b: BudgetSummary;
    base: string;
    canEdit: boolean;
    onMoveMoney?: (budgetId: number) => void;
}

export default function BudgetItem({
    b,
    base,
    canEdit,
    onMoveMoney,
}: BudgetItemProps) {
    const leftText = (left: number) =>
        left < 0 ? `Over by ${rupiah(-left)}` : `${rupiah(left)} left`;

    return (
        <Card className="h-full w-56 shrink-0 border-0 ring-0">
            <Link
                href={`${base}/budgets/${b.id}`}
                className="flex flex-1 flex-col gap-(--card-spacing)"
            >
                <CardHeader>
                    <CardTitle className="flex min-w-0 items-center gap-0.5">
                        <span className="truncate">{b.name}</span>
                        <ChevronRightIcon className="size-4 shrink-0 text-muted-foreground" />
                    </CardTitle>
                    <CardAction
                        className={cn(
                            'shrink-0 text-sm whitespace-nowrap',
                            b.over
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        {leftText(b.left)}
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <BudgetBar pct={b.pct} over={b.over} />
                    <MovedToGoals amount={b.moved} />
                    <ReturnedFromGoals amount={b.returned} />
                </CardContent>
            </Link>
            {b.over && canEdit && (
                <CardFooter className="mt-auto justify-between gap-2 border-t">
                    <span className="truncate text-xs text-muted-foreground">
                        Cover it from another budget
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="link"
                        className="h-auto shrink-0 px-0 font-semibold text-foreground"
                        onClick={() => onMoveMoney?.(b.id)}
                    >
                        Move money
                    </Button>
                </CardFooter>
            )}
        </Card>
    );
}
