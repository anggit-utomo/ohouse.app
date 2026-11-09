import type { BudgetSummary } from '@/types';
// T2 draft: `.scratch/T2-scroll-area.tsx` lands here as `@/components/ui/scroll-area`.
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
            <div className="flex items-stretch gap-3 px-4 pb-2">
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
