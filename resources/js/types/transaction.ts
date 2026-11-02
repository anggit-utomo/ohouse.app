export type Defaults = {
    type: 'expense' | 'income';
    budget_id: string;
    amount: string;
    note: string;
    occurred_at: string;
    user_id: string;
    corrects_transaction_id: number | null;
};
