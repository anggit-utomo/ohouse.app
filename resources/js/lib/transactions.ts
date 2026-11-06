import type {
    TransactionDetail,
    TransactionRow,
    TransactionType,
    TransferEndpoints,
} from '@/types';

export function isTransferType(type: TransactionType): boolean {
    return type === 'transfer_in' || type === 'transfer_out';
}

export function transferEndpoints(title: string): TransferEndpoints | null {
    if (!title.startsWith('Moved ')) {
        return null;
    }

    const parts = title.replace('Moved ', '').split(' → ');

    return parts.length === 2 ? { from: parts[0], to: parts[1] } : null;
}

export function toTransactionDetail(row: TransactionRow): TransactionDetail {
    const isTransfer = isTransferType(row.type);

    return {
        ...row,
        note: isTransfer ? null : row.note,
        transferEndpoints: isTransfer ? transferEndpoints(row.title) : null,
    };
}
