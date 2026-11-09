import type { GoalSummary } from '@/types';
import GoalItem from './goal-item';

interface GoalListProps {
    base: string;
    goals: GoalSummary[];
    onSelect?: (goal: GoalSummary) => void;
}

export default function GoalList({ base, goals, onSelect }: GoalListProps) {
    return goals.map((goal) => (
        <GoalItem key={goal.id} goal={goal} base={base} onSelect={onSelect} />
    ));
}
