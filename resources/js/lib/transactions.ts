import type {
    TransactionDetail,
    TransactionRow,
    TransactionType,
} from '@/types';

export function isTransferType(type: TransactionType): boolean {
    return type === 'transfer_in' || type === 'transfer_out';
}

export function transferMoved(title: string): {
    from: string;
    to: string;
} | null {
    if (!title.startsWith('Moved ')) {
        return null;
    }

    const parts = title.replace('Moved ', '').split(' → ');

    return parts.length === 2 ? { from: parts[0], to: parts[1] } : null;
}

export function transactionDetail(
    row: TransactionRow,
    note: string | null,
): TransactionDetail {
    return {
        id: row.id,
        type: row.type,
        amount: row.amount,
        signed: row.signed,
        note,
        budget: row.budget,
        budget_id: row.budget_id,
        user: row.user,
        user_id: row.user_id,
        occurred_at: row.occurred_at,
        left: row.left,
    };
}
