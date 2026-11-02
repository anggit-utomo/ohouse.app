import { BudgetBar } from '@/components/budget-bar';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { rupiah } from '@/lib/money';
import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';

interface BalanceComponentProps {
    base: string;
    summary: { left: number; spent: number; available: number; pct: number };
}

export default function BalanceComponent({
    base,
    summary,
}: BalanceComponentProps) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between text-muted-foreground">
                    <span className="text-sm">Remaining this month</span>
                    <Link
                        href={`${base}/report`}
                        className="flex items-center gap-1 text-sm font-medium text-foreground"
                    >
                        View report
                        <ChevronRightIcon className="size-4" />
                    </Link>
                </div>
                <CardTitle
                    className={`text-3xl font-bold ${summary.left < 0 ? 'text-destructive' : ''}`}
                >
                    {summary.left < 0 ? '-' : ''}
                    {rupiah(Math.abs(summary.left))}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <BudgetBar pct={summary.pct} over={summary.left < 0} />
            </CardContent>
            <CardFooter className="flex justify-between text-xs text-muted-foreground">
                <span>Spent {rupiah(summary.spent)}</span>
                <span>of {rupiah(summary.available)}</span>
            </CardFooter>
        </Card>
    );
}
