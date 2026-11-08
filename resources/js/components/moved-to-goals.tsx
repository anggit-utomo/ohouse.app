import { rupiah } from '@/lib/money';

export function MovedToGoals({ amount }: { amount: number }) {
    if (amount <= 0) {
        return null;
    }

    return (
        <p className="mt-1 text-xs text-muted-foreground">
            Moved {rupiah(amount)} to goals
        </p>
    );
}
