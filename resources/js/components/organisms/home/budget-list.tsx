import type { BudgetSummary } from '@/types';
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
    return budgets.map((b, idx) => (
        <BudgetItem
            key={`${b.id}-${idx}`}
            base={base}
            canEdit={canEdit}
            b={b}
            onMoveMoney={onMoveMoney}
        />
    ));
}
