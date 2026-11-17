import type { GoalSummary } from '@/types';
import { ScrollArea } from '@/components/ui/scroll-area';
import GoalItem from './goal-item';

interface GoalListProps {
    base: string;
    goals: GoalSummary[];
    onSelect?: (goal: GoalSummary) => void;
    card?: boolean;
}

export default function GoalList({
    base,
    goals,
    onSelect,
    card = false,
}: GoalListProps) {
    if (card) {
        if (goals.length === 1) {
            return (
                <div className="py-3">
                    <GoalItem
                        goal={goals[0]}
                        base={base}
                        onSelect={onSelect}
                        card
                        single
                    />
                </div>
            );
        }

        return (
            <ScrollArea
                orientation="horizontal"
                scrollBarClassName="opacity-0 data-hovering:opacity-100 data-scrolling:opacity-100"
                className="-mx-4"
            >
                <div className="flex items-stretch gap-3 py-3 pl-4 [&>*:last-child]:mr-4">
                    {goals.map((goal) => (
                        <GoalItem
                            key={goal.id}
                            goal={goal}
                            base={base}
                            onSelect={onSelect}
                            card
                        />
                    ))}
                </div>
            </ScrollArea>
        );
    }

    return goals.map((goal) => (
        <GoalItem key={goal.id} goal={goal} base={base} onSelect={onSelect} />
    ));
}
