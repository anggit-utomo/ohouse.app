export function BudgetBar({ pct, over }: { pct: number; over?: boolean }) {
    return (
        <div className="h-2 overflow-hidden rounded-full bg-muted">
            <div
                className={`h-full rounded-full ${over ? 'bg-destructive' : 'bg-primary'}`}
                style={{ width: `${pct}%` }}
            />
        </div>
    );
}
