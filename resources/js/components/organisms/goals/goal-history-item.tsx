import { ChevronRightIcon } from 'lucide-react';
import { rupiah } from '@/lib/money';
import type { GoalArchived } from '@/types';

const archivedOn = (date: string) =>
    new Date(date).toLocaleDateString('en-GB', {
        month: 'short',
        year: 'numeric',
    });

export default function GoalHistoryItem({
    goal,
    onSelect,
}: {
    goal: GoalArchived;
    onSelect: (goal: GoalArchived) => void;
}) {
    return (
        <button
            type="button"
            className="block w-full rounded-xl border p-3 text-left"
            onClick={() => onSelect(goal)}
        >
            <div className="flex items-baseline justify-between gap-2">
                <span className="flex items-center gap-0.5 font-semibold">
                    {goal.name}
                    <ChevronRightIcon className="size-4 text-muted-foreground" />
                </span>
                {goal.archived_at && (
                    <span className="text-xs text-muted-foreground">
                        Archived {archivedOn(goal.archived_at)}
                    </span>
                )}
            </div>
            <div className="mt-1 flex justify-between text-xs text-muted-foreground">
                <span>{rupiah(goal.saved)} saved</span>
                <span>of {rupiah(goal.target)}</span>
            </div>
        </button>
    );
}
