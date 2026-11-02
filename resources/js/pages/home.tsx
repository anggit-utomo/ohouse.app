import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowRightLeftIcon, PlusIcon } from 'lucide-react';
import { BottomNav } from '@/components/bottom-nav';
import { Button } from '@/components/ui/button';
import HeadTitle from '@/components/head-title';
import BalanceComponent from '@/components/organisms/home/balance';
import AlertBanner from '@/components/organisms/home/alert-banner';
import BudgetEmpty from '@/components/organisms/home/budget-empty';
import BudgetList from '@/components/organisms/home/budget-list';
import { TransactionDrawer } from '@/components/organisms/transactions/transaction-drawer';
import { Budget, Defaults } from '@/types';

type Props = {
    household: { id: number };
    monthLabel: string;
    periodRange: string;
    summary: { left: number; spent: number; available: number; pct: number };
    banner: { days: number } | null;
    budgets: Budget[];
    members: { id: number; name: string }[];
    canEdit: boolean;
    defaults: Defaults;
    today: string;
};

export default function Home({
    household,
    monthLabel,
    periodRange,
    summary,
    banner,
    budgets,
    members,
    canEdit,
    defaults,
    today,
}: Props) {
    const base = `/h/${household.id}`;
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);

    return (
        <>
            <Head title="Home" />

            <HeadTitle
                left={
                    <>
                        <h1 className="text-xl font-semibold">{monthLabel}</h1>
                        <p className="text-xs text-muted-foreground">
                            {periodRange}
                        </p>
                    </>
                }
                right={members.slice(0, 3).map((m) => (
                    <span
                        key={m.id}
                        title={m.name}
                        className="flex size-8 items-center justify-center rounded-full border-2 border-background bg-muted text-xs font-semibold"
                    >
                        {m.name.charAt(0).toUpperCase()}
                    </span>
                ))}
            />

            <BalanceComponent base={base} summary={summary} />

            <Button type="button" onClick={() => setIsDrawerOpen(true)}>
                <PlusIcon className="mr-1 size-4" /> Transaction
            </Button>

            {banner && <AlertBanner base={base} banner={banner} />}

            <HeadTitle
                className="mt-2"
                left={<h2 className="text-base font-semibold">Budgets</h2>}
                right={
                    canEdit && (
                        <div className="flex items-center gap-2">
                            <Button size="sm" variant="outline" asChild>
                                <Link
                                    href={`${base}/transfers/create`}
                                    className="text-xs"
                                >
                                    <ArrowRightLeftIcon className="size-4" />{' '}
                                    Move money
                                </Link>
                            </Button>
                            <Button size="sm" variant="outline" asChild>
                                <Link
                                    href={`${base}/budgets`}
                                    className="text-xs font-medium"
                                >
                                    Edit
                                </Link>
                            </Button>
                        </div>
                    )
                }
            />

            {budgets.length === 0 ? (
                <BudgetEmpty base={base} canEdit={canEdit} />
            ) : (
                <BudgetList budgets={budgets} base={base} canEdit={canEdit} />
            )}

            <BottomNav
                householdId={household.id}
                active="home"
                canAdd={canEdit}
            />

            <TransactionDrawer
                open={isDrawerOpen}
                onOpenChange={setIsDrawerOpen}
                householdId={household.id}
                defaults={defaults}
                budgets={budgets}
                members={members}
                today={today}
            />
        </>
    );
}
