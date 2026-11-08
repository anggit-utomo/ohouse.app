export type BudgetSummary = {
    id: number;
    name: string;
    left: number;
    spent: number;
    available: number;
    moved: number;
    pct: number;
    over: boolean;
};

export type BudgetOption = {
    id: number;
    name: string;
    left: number;
};
