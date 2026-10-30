import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    household: { id: number; name: string; currency: string };
    user: { name: string; email: string };
    isOwner: boolean;
};

export default function HouseholdSettings({ household, user, isOwner }: Props) {
    const base = `/h/${household.id}`;
    const name = useForm({ name: household.name });
    const del = useForm({ confirm: '' });

    return (
        <>
            <Head title="Settings" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-10">
                <Link
                    href={`${base}/family`}
                    className="flex items-center gap-1 text-lg font-semibold"
                >
                    <ChevronLeft className="size-5" />
                    Settings
                </Link>

                <div className="flex items-center gap-3 rounded-xl border p-4">
                    <span className="flex size-8 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                        {user.name.charAt(0).toUpperCase()}
                    </span>
                    <span className="flex-1">
                        <b className="block">{user.name}</b>
                        <span className="text-sm text-muted-foreground">
                            {user.email}
                        </span>
                    </span>
                    <Link
                        href="/settings/profile"
                        className="text-sm font-medium"
                    >
                        Edit
                    </Link>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="name">Household name</Label>
                    <div className="flex gap-2">
                        <Input
                            id="name"
                            value={name.data.name}
                            disabled={!isOwner}
                            onChange={(e) =>
                                name.setData('name', e.target.value)
                            }
                        />
                        {isOwner && (
                            <Button
                                variant="outline"
                                disabled={
                                    name.processing ||
                                    name.data.name === household.name
                                }
                                onClick={() =>
                                    name.put(`${base}/settings`, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Save
                            </Button>
                        )}
                    </div>
                    {name.errors.name && (
                        <p className="text-sm text-destructive">
                            {name.errors.name}
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground">
                        Currency: {household.currency}
                    </p>
                </div>

                <Button
                    variant="outline"
                    onClick={() => router.post('/logout')}
                >
                    Sign out
                </Button>

                {isOwner && (
                    <div className="grid gap-2 rounded-xl border border-destructive/40 p-4">
                        <p className="font-semibold text-destructive">
                            Delete household
                        </p>
                        <p className="text-sm text-muted-foreground">
                            This deletes every budget and transaction for
                            everyone, and can't be undone. Type "
                            {household.name}" to confirm.
                        </p>
                        <Input
                            value={del.data.confirm}
                            onChange={(e) =>
                                del.setData('confirm', e.target.value)
                            }
                        />
                        {del.errors.confirm && (
                            <p className="text-sm text-destructive">
                                {del.errors.confirm}
                            </p>
                        )}
                        <Button
                            variant="outline"
                            className="text-destructive"
                            disabled={
                                del.data.confirm !== household.name ||
                                del.processing
                            }
                            onClick={() => del.delete(`${base}/settings`)}
                        >
                            Delete household
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
