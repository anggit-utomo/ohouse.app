import { Budget } from '@/types';
import BudgetItem from './budget-item';

interface BudgetListProps {
    base: string;
    budgets: Budget[];
    canEdit: boolean;
}

export default function BudgetList({
    base,
    budgets,
    canEdit,
}: BudgetListProps) {
    return budgets.map((b, idx) => (
        <BudgetItem
            key={`${b.id}-${idx}`}
            base={base}
            canEdit={canEdit}
            b={b}
        />
    ));
}
