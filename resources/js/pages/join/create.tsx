import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type Props = {
    code: string;
    preview: { name: string; budgets: number; members: number } | null;
    invalid: boolean;
};

export default function Join({ code, preview, invalid }: Props) {
    const form = useForm({ code });

    return (
        <>
            <Head title="Join a family budget" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-28">
                <h1 className="text-lg font-semibold">Join a family budget</h1>
                <p className="text-muted-foreground">
                    Scan the QR code with your phone's camera, or type the pair
                    code your partner shows you.
                </p>

                <div className="grid gap-2">
                    <label htmlFor="code" className="text-sm font-medium">
                        Pair code
                    </label>
                    <input
                        id="code"
                        autoCapitalize="characters"
                        autoComplete="off"
                        placeholder="KTR-4829"
                        className="h-14 rounded-md border bg-background px-3 text-center font-mono text-2xl font-bold tracking-widest"
                        value={form.data.code}
                        onChange={(e) =>
                            form.setData('code', e.target.value.toUpperCase())
                        }
                    />
                    {(form.errors.code || invalid) && (
                        <p className="text-sm text-destructive">
                            {form.errors.code ??
                                'That code is invalid or has expired.'}
                        </p>
                    )}
                </div>

                {preview && (
                    <div className="rounded-xl border p-4">
                        <p className="text-sm text-muted-foreground">
                            You will join
                        </p>
                        <p className="text-base font-semibold">
                            {preview.name}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {preview.budgets} budgets · {preview.members}{' '}
                            {preview.members === 1 ? 'member' : 'members'} · you
                            can edit
                        </p>
                    </div>
                )}
            </div>

            <div className="fixed inset-x-0 bottom-0 border-t bg-background p-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                <div className="mx-auto max-w-md">
                    <Button
                        className="h-11 w-full"
                        disabled={
                            form.processing || form.data.code.trim() === ''
                        }
                        onClick={() => form.post('/join')}
                    >
                        Join budget
                    </Button>
                </div>
            </div>
        </>
    );
}
