import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppearance } from '@/hooks/use-appearance';

type Props = {
    household: { id: number; name: string; currency: string };
    currencies: { code: string; name: string; symbol: string }[];
    currencyLocked: boolean;
    user: { name: string; email: string; verified: boolean };
    isOwner: boolean;
    ownsHousehold: boolean;
};

function Section({
    title,
    hint,
    children,
}: {
    title: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-3 rounded-xl border p-4">
            <div>
                <h2 className="font-semibold">{title}</h2>
                {hint && (
                    <p className="text-sm text-muted-foreground">{hint}</p>
                )}
            </div>
            {children}
        </section>
    );
}

function Field({
    label,
    id,
    error,
    children,
}: {
    label: string;
    id: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export default function Settings({
    household,
    currencies,
    currencyLocked,
    user,
    isOwner,
    ownsHousehold,
}: Props) {
    const base = `/h/${household.id}`;
    const { appearance, updateAppearance } = useAppearance();

    const profile = useForm({ name: user.name, email: user.email });
    const home = useForm({
        name: household.name,
        currency: household.currency,
    });
    const account = useForm({ password: '' });
    const del = useForm({ confirm: '' });

    const saveHousehold = () => {
        const currencyChanged = home.data.currency !== household.currency;

        home.put(`${base}/settings`, {
            preserveScroll: true,
            // so every amount is formatted in the new currency
            onSuccess: () => currencyChanged && window.location.reload(),
        });
    };

    return (
        <>
            <Head title="Settings" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-10">
                <PageHeader title="Settings" href={`${base}/family`} />

                <Section title="Your profile">
                    <div className="flex items-center gap-3">
                        <span className="flex size-12 items-center justify-center rounded-full bg-muted text-lg font-semibold">
                            {user.name.charAt(0).toUpperCase()}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {user.verified
                                ? 'Email verified'
                                : 'Email not verified yet'}
                        </span>
                    </div>
                    <Field label="Name" id="name" error={profile.errors.name}>
                        <Input
                            id="name"
                            value={profile.data.name}
                            onChange={(e) =>
                                profile.setData('name', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Email"
                        id="email"
                        error={profile.errors.email}
                    >
                        <Input
                            id="email"
                            type="email"
                            value={profile.data.email}
                            onChange={(e) =>
                                profile.setData('email', e.target.value)
                            }
                        />
                    </Field>
                    <Button
                        disabled={profile.processing || !profile.isDirty}
                        onClick={() =>
                            profile.patch('/settings/profile', {
                                preserveScroll: true,
                            })
                        }
                    >
                        Save profile
                    </Button>
                </Section>

                <Section
                    title="Household"
                    hint={
                        isOwner ? undefined : 'Only the owner can change these.'
                    }
                >
                    <Field
                        label="Name"
                        id="household-name"
                        error={home.errors.name}
                    >
                        <Input
                            id="household-name"
                            value={home.data.name}
                            disabled={!isOwner}
                            onChange={(e) =>
                                home.setData('name', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Currency"
                        id="currency"
                        error={home.errors.currency}
                    >
                        <Select
                            value={home.data.currency}
                            disabled={!isOwner || currencyLocked}
                            onValueChange={(v) => home.setData('currency', v)}
                        >
                            <SelectTrigger id="currency" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {currencies.map((c) => (
                                    <SelectItem key={c.code} value={c.code}>
                                        {c.symbol} · {c.name} ({c.code})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {currencyLocked && (
                            <p className="text-xs text-muted-foreground">
                                The currency is fixed once transactions have
                                been recorded.
                            </p>
                        )}
                    </Field>
                    {isOwner && (
                        <Button
                            disabled={home.processing || !home.isDirty}
                            onClick={saveHousehold}
                        >
                            Save household
                        </Button>
                    )}
                    <Link
                        href={`${base}/family`}
                        className="text-sm font-medium underline"
                    >
                        Members and pair code
                    </Link>
                </Section>

                <Section title="Appearance">
                    <div className="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1">
                        {(['light', 'dark', 'system'] as const).map((mode) => (
                            <Button
                                key={mode}
                                size="sm"
                                type="button"
                                variant={
                                    appearance === mode ? 'default' : 'ghost'
                                }
                                onClick={() => updateAppearance(mode)}
                            >
                                {mode.charAt(0).toUpperCase() + mode.slice(1)}
                            </Button>
                        ))}
                    </div>
                </Section>

                {/* <Section
                    title="Security"
                    hint="Password, two-factor authentication and passkeys."
                >
                    <Button variant="outline" asChild>
                        <Link href={`${base}/settings/security`}>
                            Open security settings
                        </Link>
                    </Button>
                </Section> */}

                <Button
                    variant="outline"
                    onClick={() => router.post('/logout')}
                >
                    Sign out
                </Button>

                <Section title="Danger zone">
                    {isOwner && (
                        <div className="grid gap-2">
                            <p className="font-medium text-destructive">
                                Delete household
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Deletes every budget and transaction for
                                everyone, and can't be undone. Type "
                                {household.name}" to confirm.
                            </p>
                            <Input
                                aria-label="Type the household name"
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

                    <div className="grid gap-2">
                        <p className="font-medium text-destructive">
                            Delete my account
                        </p>
                        {ownsHousehold ? (
                            <p className="text-sm text-muted-foreground">
                                You own a household. Delete the household first,
                                then you can delete your account.
                            </p>
                        ) : (
                            <>
                                <p className="text-sm text-muted-foreground">
                                    Removes you from the household. Entries with
                                    your name stay in the household's history,
                                    shown as "Former member".
                                </p>
                                <Input
                                    aria-label="Your password"
                                    type="password"
                                    placeholder="Your password"
                                    value={account.data.password}
                                    onChange={(e) =>
                                        account.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                />
                                {account.errors.password && (
                                    <p className="text-sm text-destructive">
                                        {account.errors.password}
                                    </p>
                                )}
                                <Button
                                    variant="outline"
                                    className="text-destructive"
                                    disabled={
                                        !account.data.password ||
                                        account.processing
                                    }
                                    onClick={() =>
                                        account.delete('/settings/profile')
                                    }
                                >
                                    Delete my account
                                </Button>
                            </>
                        )}
                    </div>
                </Section>
            </div>
        </>
    );
}
