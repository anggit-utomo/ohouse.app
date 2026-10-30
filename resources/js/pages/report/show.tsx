import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { BudgetBar } from '@/components/budget-bar';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { rupiah } from '@/lib/money';

type Item = { id: number; name: string; spent: number; pct: number };

type Props = {
    household: { id: number };
    month: string;
    months: { value: string; label: string }[];
    hasData: boolean;
    spent: number;
    income: number;
    previous: { label: string; spent: number } | null;
    change: number | null;
    budgets: Item[];
    people: Item[];
};

const shades = [
    'bg-primary',
    'bg-muted-foreground',
    'bg-border',
    'bg-secondary',
];

const compare = (change: number | null, previous: Props['previous']) => {
    if (change === null || !previous) return 'No numbers for the month before';
    if (change === 0) return `Same as ${previous.label}`;

    return `${rupiah(Math.abs(change))} ${change < 0 ? 'less' : 'more'} than ${previous.label}`;
};

export default function Report({
    household,
    month,
    months,
    hasData,
    spent,
    income,
    previous,
    change,
    budgets,
    people,
}: Props) {
    const base = `/h/${household.id}`;

    return (
        <>
            <Head title="Report" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-3 p-4 pb-10">
                <Link
                    href={base}
                    className="flex items-center gap-1 text-lg font-semibold"
                >
                    <ChevronLeft className="size-5" />
                    Report
                </Link>

                <Select
                    value={month}
                    onValueChange={(m) =>
                        router.get(
                            `${base}/report`,
                            { month: m },
                            { preserveScroll: true },
                        )
                    }
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {months.map((m) => (
                            <SelectItem key={m.value} value={m.value}>
                                {m.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="rounded-xl border p-4">
                    <p className="text-muted-foreground">Total spent</p>
                    <p className="my-1 text-3xl font-bold">{rupiah(spent)}</p>
                    <p className="text-sm text-muted-foreground">
                        {compare(change, previous)}
                    </p>
                    {income > 0 && (
                        <p className="mt-1 text-sm text-green-700">
                            Income {rupiah(income)}
                        </p>
                    )}
                </div>

                {!hasData && (
                    <p className="py-10 text-center text-muted-foreground">
                        Nothing recorded in this month yet.
                    </p>
                )}

                {budgets.length > 0 && (
                    <>
                        <h2 className="mt-3 text-base font-semibold">
                            By budget
                        </h2>
                        {budgets.map((b) => (
                            <div key={b.id} className="py-1">
                                <div className="mb-1.5 flex items-center justify-between">
                                    <span>{b.name}</span>
                                    <span className="text-sm">
                                        <b>{rupiah(b.spent)}</b>{' '}
                                        <span className="text-muted-foreground">
                                            {b.pct}%
                                        </span>
                                    </span>
                                </div>
                                <BudgetBar pct={b.pct} />
                            </div>
                        ))}
                    </>
                )}

                {people.length > 0 && (
                    <>
                        <h2 className="mt-3 text-base font-semibold">
                            By person
                        </h2>
                        <div className="flex h-3 overflow-hidden rounded-full bg-muted">
                            {people.map((p, i) => (
                                <div
                                    key={p.id}
                                    className={shades[i % shades.length]}
                                    style={{ width: `${p.pct}%` }}
                                />
                            ))}
                        </div>
                        {people.map((p, i) => (
                            <div
                                key={p.id}
                                className="flex items-center justify-between text-sm"
                            >
                                <span className="flex items-center gap-2">
                                    <span
                                        className={`size-2.5 rounded-full ${shades[i % shades.length]}`}
                                    />
                                    {p.name}
                                </span>
                                <span>
                                    <b>{rupiah(p.spent)}</b>{' '}
                                    <span className="text-muted-foreground">
                                        {p.pct}%
                                    </span>
                                </span>
                            </div>
                        ))}
                    </>
                )}
            </div>
        </>
    );
}
