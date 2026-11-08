import { Button } from '@/components/ui/button';

interface GoalEmptyProps {
    canEdit: boolean;
    onNew: () => void;
}

export default function GoalEmpty({ canEdit, onNew }: GoalEmptyProps) {
    return (
        <div className="rounded-xl border p-6 text-center">
            <p className="font-semibold">No goals yet</p>
            <p className="mb-4 text-sm text-muted-foreground">
                Save for a trip, a new laptop, or a rainy day.
            </p>
            {canEdit && (
                <Button type="button" onClick={onNew}>
                    New goal
                </Button>
            )}
        </div>
    );
}
