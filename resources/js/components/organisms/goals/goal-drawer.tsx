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
import type { GoalDetail } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    householdId: number;
    goal?: GoalDetail | null;
};

type Data = {
    name: string;
    target_amount: string;
    target_date: string;
    sort: string;
};

const empty: Data = {
    name: '',
    target_amount: '',
    target_date: '',
    sort: '',
};

export function GoalDrawer({
    open,
    onOpenChange,
    householdId,
    goal = null,
}: Props) {
    const form = useForm<Data>(
        goal
            ? {
                  name: goal.name,
                  target_amount: String(goal.target),
                  target_date: goal.target_date ?? '',
                  sort: String(goal.sort),
              }
            : empty,
    );

    const base = `/h/${householdId}`;
    const errors = form.errors as Record<string, string>;
    const error =
        errors.name ??
        errors.target_amount ??
        errors.target_date ??
        errors.sort;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((d) => ({
            name: d.name,
            target_amount: Number(d.target_amount || 0),
            target_date: d.target_date || null,
            sort: d.sort === '' ? null : Number(d.sort),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (goal) {
            form.patch(`${base}/goals/${goal.id}`, options);
        } else {
            form.post(`${base}/goals`, options);
        }
    };

    return (
        <Drawer open={open} onOpenChange={onOpenChange}>
            <DrawerContent>
                <div className="mx-auto w-full max-w-sm">
                    <form onSubmit={submit}>
                        <div className="space-y-4">
                            <DrawerHeader className="px-0">
                                <DrawerTitle>
                                    {goal ? 'Edit goal' : 'New goal'}
                                </DrawerTitle>
                                <DrawerDescription>
                                    Set aside money for something worth saving
                                    for.
                                </DrawerDescription>
                            </DrawerHeader>

                            <div className="grid gap-3 px-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="goal-name">Name</Label>
                                    <Input
                                        id="goal-name"
                                        placeholder="Bali trip"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="goal-target">Target</Label>
                                    <div className="relative">
                                        <span className="absolute top-2 left-3 text-sm text-muted-foreground">
                                            {currency().symbol}
                                        </span>
                                        <Input
                                            id="goal-target"
                                            inputMode="numeric"
                                            className="pl-10 text-right font-semibold"
                                            placeholder="0"
                                            value={
                                                form.data.target_amount
                                                    ? plain(
                                                          Number(
                                                              form.data
                                                                  .target_amount,
                                                          ),
                                                      )
                                                    : ''
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'target_amount',
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
                                    <Label htmlFor="goal-date">Deadline</Label>
                                    <Input
                                        id="goal-date"
                                        type="date"
                                        value={form.data.target_date}
                                        onChange={(e) =>
                                            form.setData(
                                                'target_date',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="goal-sort">Order</Label>
                                    <Input
                                        id="goal-sort"
                                        inputMode="numeric"
                                        placeholder="Next"
                                        value={form.data.sort}
                                        onChange={(e) =>
                                            form.setData(
                                                'sort',
                                                e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
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
                                        form.processing ||
                                        !form.data.name.trim() ||
                                        !form.data.target_amount
                                    }
                                >
                                    {form.processing ? (
                                        <>
                                            <Spinner /> Saving...
                                        </>
                                    ) : (
                                        'Save goal'
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
