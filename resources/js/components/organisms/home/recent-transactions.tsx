import { Link } from '@inertiajs/react';
import { index } from '@/routes/transactions';
import type { TransactionRow } from '@/types';
import TransactionListItem from '../transactions/transaction-list-item';

interface RecentTransactionsProps {
    householdId: number;
    rows: TransactionRow[];
    onSelect: (row: TransactionRow) => void;
}

export default function RecentTransactions({
    householdId,
    rows,
    onSelect,
}: RecentTransactionsProps) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <section>
            <div className="flex items-center justify-between">
                <h2 className="text-base font-semibold">Recent transactions</h2>
                <Link
                    href={index.url({ household: householdId })}
                    className="text-xs font-medium"
                >
                    View all
                </Link>
            </div>

            <div>
                {rows.map((row) => (
                    <TransactionListItem
                        key={row.id}
                        row={row}
                        onSelect={onSelect}
                    />
                ))}
            </div>
        </section>
    );
}
