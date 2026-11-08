import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { currency, plain } from '@/lib/money';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    householdId: number;
    goalId: number;
    today: string;
};

type Data = {
    amount: string;
    note: string;
    occurred_at: string;
};

export function GoalContributionDrawer({
    open,
    onOpenChange,
    householdId,
    goalId,
    today,
}: Props) {
    const base = `/h/${householdId}`;
    const form = useForm<Data>({
        amount: '',
        note: '',
        occurred_at: today,
    });

    const errors = form.errors as Record<string, string>;
    const error =
        errors.amount ?? errors.note ?? errors.occurred_at ?? errors.goal;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((d) => ({
            amount: Number(d.amount || 0),
            note: d.note || null,
            occurred_at: d.occurred_at || null,
        }));

        form.post(`${base}/goals/${goalId}/movements`, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        });
    };

    return (
        <Drawer open={open} onOpenChange={onOpenChange}>
            <DrawerContent>
                <div className="mx-auto w-full max-w-sm">
                    <form onSubmit={submit}>
                        <div className="space-y-4">
                            <DrawerHeader className="px-0">
                                <DrawerTitle>Add money</DrawerTitle>
                                <DrawerDescription>
                                    Money that never entered the monthly
                                    budgets.
                                </DrawerDescription>
                            </DrawerHeader>

                            <div className="grid gap-3 px-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="contribution-amount">
                                        Amount
                                    </Label>
                                    <div className="relative">
                                        <span className="absolute top-2 left-3 text-sm text-muted-foreground">
                                            {currency().symbol}
                                        </span>
                                        <Input
                                            id="contribution-amount"
                                            inputMode="numeric"
                                            className="pl-10 text-right font-semibold"
                                            placeholder="0"
                                            value={
                                                form.data.amount
                                                    ? plain(
                                                          Number(
                                                              form.data.amount,
                                                          ),
                                                      )
                                                    : ''
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'amount',
                                                    e.target.value.replace(
                                                        /\D/g,
                                                        '',
                                                    ),
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="contribution-note">
                                        Note
                                    </Label>
                                    <Input
                                        id="contribution-note"
                                        placeholder="Optional"
                                        value={form.data.note}
                                        onChange={(e) =>
                                            form.setData('note', e.target.value)
                                        }
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="contribution-date">
                                        Date
                                    </Label>
                                    <Input
                                        id="contribution-date"
                                        type="date"
                                        max={today}
                                        value={form.data.occurred_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'occurred_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                {error && (
                                    <p className="text-sm text-destructive">
                                        {error}
                                    </p>
                                )}
                            </div>
                        </div>

                        <DrawerFooter className="pb-6">
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing || !form.data.amount
                                    }
                                >
                                    {form.processing ? (
                                        <>
                                            <Spinner /> Adding...
                                        </>
                                    ) : (
                                        'Add money'
                                    )}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </DrawerFooter>
                    </form>
                </div>
            </DrawerContent>
        </Drawer>
    );
}
