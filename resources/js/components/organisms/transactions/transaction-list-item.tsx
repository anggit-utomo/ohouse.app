import { Badge } from '@/components/ui/badge';
import { rupiah } from '@/lib/money';
import { isTransferType } from '@/lib/transactions';
import { cn } from '@/lib/utils';
import type { TransactionRow } from '@/types';

const timeLabel = (iso: string) =>
    new Date(iso).toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
    });

const amountLabel = (row: TransactionRow) =>
    isTransferType(row.type)
        ? rupiah(row.amount)
        : `${row.signed > 0 ? '+' : '-'}${rupiah(Math.abs(row.signed))}`;

export default function TransactionListItem({
    row,
    onSelect,
}: {
    row: TransactionRow;
    onSelect?: (row: TransactionRow) => void;
}) {
    const body = (
        <>
            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                {row.user.charAt(0).toUpperCase()}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate font-semibold">
                    {row.title}
                </span>
                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Badge variant="outline">{row.budget}</Badge>
                    {timeLabel(row.occurred_at)}
                </span>
            </span>
            <span
                className={cn(
                    'font-semibold',
                    row.signed > 0 && 'text-green-700',
                )}
            >
                {amountLabel(row)}
            </span>
        </>
    );

    if (onSelect) {
        return (
            <button
                type="button"
                className="flex w-full items-center gap-3 py-2.5 text-left"
                onClick={() => onSelect(row)}
            >
                {body}
            </button>
        );
    }

    return (
        <div className="flex w-full items-center gap-3 py-2.5 text-left">
            {body}
        </div>
    );
}
