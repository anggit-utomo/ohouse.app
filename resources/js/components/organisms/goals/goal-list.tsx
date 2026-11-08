import type { GoalSummary } from '@/types';
import GoalItem from './goal-item';

interface GoalListProps {
    base: string;
    goals: GoalSummary[];
}

export default function GoalList({ base, goals }: GoalListProps) {
    return goals.map((goal) => (
        <GoalItem key={goal.id} goal={goal} base={base} />
    ));
}
