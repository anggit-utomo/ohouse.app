import type { BudgetSummary } from '@/types';
import { ScrollArea } from '@/components/ui/scroll-area';
import BudgetItem from './budget-item';

interface BudgetListProps {
    base: string;
    budgets: BudgetSummary[];
    canEdit: boolean;
    onMoveMoney?: (budgetId: number) => void;
}

export default function BudgetList({
    base,
    budgets,
    canEdit,
    onMoveMoney,
}: BudgetListProps) {
    return (
        <ScrollArea
            orientation="horizontal"
            scrollBarClassName="opacity-0 data-hovering:opacity-100 data-scrolling:opacity-100"
            className="-mx-4"
        >
            <div className="flex items-stretch gap-3 pt-2 pb-2 pl-4 [&>*:last-child]:mr-4">
                {budgets.map((b) => (
                    <BudgetItem
                        key={b.id}
                        b={b}
                        base={base}
                        canEdit={canEdit}
                        onMoveMoney={onMoveMoney}
                    />
                ))}
            </div>
        </ScrollArea>
    );
}
