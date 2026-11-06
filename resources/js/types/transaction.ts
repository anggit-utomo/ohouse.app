export type TransactionType =
    | 'expense'
    | 'income'
    | 'transfer_out'
    | 'transfer_in';

export type TransactionRow = {
    id: number;
    title: string;
    budget: string;
    user: string;
    type: TransactionType;
    amount: number;
    signed: number;
    occurred_at: string;
    note?: string | null;
    left?: number;
    budget_id?: number;
    user_id?: number;
};

export type TransactionDetail = {
    id: number;
    type: TransactionType;
    amount: number;
    signed: number;
    note: string | null;
    budget: string;
    budget_id?: number;
    user: string;
    user_id?: number;
    occurred_at: string;
    left: number;
};

export type TransactionDefaults = {
    type: 'expense' | 'income';
    budget_id: string;
    amount: string;
    note: string;
    occurred_at: string;
    user_id: string;
    corrects_transaction_id?: number | null;
};

export type TransferDefaults = {
    from_budget_id: string;
    to_budget_id: string;
    amount: string;
    note: string;
};
