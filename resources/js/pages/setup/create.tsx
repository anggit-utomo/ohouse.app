import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = { suggestions: string[] };

export default function Setup({ suggestions }: Props) {
    const form = useForm<{ name: string; budgets: string[] }>({
        name: 'Our household',
        budgets: suggestions.slice(0, 5),
    });
    const [custom, setCustom] = useState('');
    const all = [
        ...suggestions,
        ...form.data.budgets.filter((b) => !suggestions.includes(b)),
    ];

    const toggle = (name: string) =>
        form.setData(
            'budgets',
            form.data.budgets.includes(name)
                ? form.data.budgets.filter((b) => b !== name)
                : [...form.data.budgets, name],
        );

    const addCustom = () => {
        const name = custom.trim();
        if (name && !form.data.budgets.includes(name))
            form.setData('budgets', [...form.data.budgets, name]);
        setCustom('');
    };

    return (
        <>
            <Head title="Set up your household" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-28">
                <h1 className="pt-4 text-2xl font-bold">
                    Set up your household
                </h1>
                <p className="text-muted-foreground">
                    You can change all of this later. Your partner joins
                    afterwards with a pair code.
                </p>

                <div className="grid gap-2">
                    <Label htmlFor="name">Household name</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    {form.errors.name && (
                        <p className="text-sm text-destructive">
                            {form.errors.name}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label>Starting budgets</Label>
                    <div className="flex flex-wrap gap-2">
                        {all.map((name) => (
                            <Button
                                key={name}
                                type="button"
                                size="sm"
                                className="rounded-full"
                                variant={
                                    form.data.budgets.includes(name)
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => toggle(name)}
                            >
                                {name}
                            </Button>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <Input
                            placeholder="Another budget"
                            value={custom}
                            onChange={(e) => setCustom(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && addCustom()}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={addCustom}
                        >
                            Add
                        </Button>
                    </div>
                </div>

                <Button variant="outline" asChild>
                    <Link href="/join">I have a pair code instead</Link>
                </Button>
            </div>

            <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                <div className="mx-auto max-w-md">
                    <Button
                        className="h-11 w-full"
                        disabled={
                            form.processing || form.data.name.trim() === ''
                        }
                        onClick={() => form.post('/setup', { replace: true })}
                    >
                        Continue to set amounts
                    </Button>
                </div>
            </div>
        </>
    );
}
