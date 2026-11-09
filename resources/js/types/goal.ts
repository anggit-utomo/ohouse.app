export type GoalSummary = {
    id: number;
    name: string;
    sort: number;
    balance: number;
    target: number;
    pct: number;
    remaining: number;
    over: boolean;
};

export type GoalDetail = GoalSummary & {
    target_date: string | null;
    archived_at: string | null;
    movements: GoalMovementRow[];
    savings: GoalSaving[];
    sweepBudgetIds: number[];
    sweepBudgets: SweepBudget[];
};

export type GoalArchived = GoalDetail & {
    saved: number;
};

export type SweepBudget = {
    id: number;
    name: string;
};

export type GoalSaving = {
    period_start: string;
    label: string;
    saved: number;
    withdrawn: number;
};

export type GoalMovementRow = {
    id: number;
    type: 'contribution' | 'withdrawal';
    amount: number;
    signed: number;
    note: string | null;
    user: string;
    occurred_at: string;
    period_start: string;
    can_remove: boolean;
};
