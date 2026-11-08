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
};
