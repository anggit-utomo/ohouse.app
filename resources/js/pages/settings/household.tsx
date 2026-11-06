import { useState, type ReactNode } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useAppearance } from '@/hooks/use-appearance';
import { NotificationsSection } from '@/components/notification-section';
import HeadTitle from '@/components/head-title';
import { BottomNav } from '@/components/bottom-nav';
import { LogOutIcon } from 'lucide-react';
import { setIfNotNull } from '@/lib/utils';
import type { HouseholdDetails, PushDevice } from '@/types';

type Props = {
    household: HouseholdDetails;
    currencies: { code: string; name: string; symbol: string }[];
    currencyLocked: boolean;
    user: { name: string; email: string; verified: boolean };
    isOwner: boolean;
    ownsHousehold: boolean;
    push: {
        publicKey: string | null;
        devices: PushDevice[];
    };
    notify: { entries: boolean; amounts: boolean };
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
    push,
    notify,
}: Props) {
    const householdBase = `/h/${household.id}`;
    const settingsBase = `/h/${household.id}/settings`;

    const { appearance, updateAppearance } = useAppearance();
    const [isConfirmingDeleteHousehold, setIsConfirmingDeleteHousehold] =
        useState(false);

    const profile = useForm({ name: user.name, email: user.email });
    const home = useForm({
        name: household.name,
        currency: household.currency,
    });
    const account = useForm({ password: '' });
    const del = useForm({ confirm: '' });

    const saveHousehold = () => {
        const currencyChanged = home.data.currency !== household.currency;

        home.put(settingsBase, {
            preserveScroll: true,
            onSuccess: () => currencyChanged && window.location.reload(),
        });
    };

    const handleDeleteHousehold = () => {
        del.delete(settingsBase, {
            onFinish: () => setIsConfirmingDeleteHousehold(false),
        });
    };

    return (
        <>
            <Head title="Settings" />

            <HeadTitle
                left={
                    <h2 className="text-xl font-semibold">Profile Settings</h2>
                }
            />

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
                <Field label="Email" id="email" error={profile.errors.email}>
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
                        profile.patch(`${settingsBase}/profile`, {
                            preserveScroll: true,
                        })
                    }
                >
                    {profile.processing ? (
                        <>
                            <Spinner /> Saving
                        </>
                    ) : (
                        'Save profile'
                    )}
                </Button>
            </Section>

            <Section
                title="Household"
                hint={isOwner ? undefined : 'Only the owner can change these.'}
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
                        onChange={(e) => home.setData('name', e.target.value)}
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
                        onValueChange={setIfNotNull((v) =>
                            home.setData('currency', v),
                        )}
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
                            The currency is fixed once transactions have been
                            recorded.
                        </p>
                    )}
                </Field>
                {isOwner && (
                    <Button
                        disabled={home.processing || !home.isDirty}
                        onClick={saveHousehold}
                    >
                        {home.processing ? (
                            <>
                                <Spinner /> Saving
                            </>
                        ) : (
                            'Save household'
                        )}
                    </Button>
                )}
                <Link
                    href={`${householdBase}/family`}
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
                            variant={appearance === mode ? 'default' : 'ghost'}
                            onClick={() => updateAppearance(mode)}
                        >
                            {mode.charAt(0).toUpperCase() + mode.slice(1)}
                        </Button>
                    ))}
                </div>
            </Section>

            <NotificationsSection
                base={settingsBase}
                publicKey={push.publicKey}
                devices={push.devices}
                notify={notify}
            />

            <Section
                title="Security"
                hint="Password, two-factor authentication and passkeys."
            >
                <Button
                    variant="outline"
                    render={<Link href={`${settingsBase}/security`} />}
                >
                    Open security settings
                </Button>
            </Section>

            <Section title="Danger zone">
                {isOwner && (
                    <div className="grid gap-2">
                        <p className="font-medium text-destructive">
                            Delete household
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Deletes every budget and transaction for everyone,
                            and can't be undone. Type "{household.name}" to
                            confirm.
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
                            onClick={() => setIsConfirmingDeleteHousehold(true)}
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
                                your name stay in the household's history, shown
                                as "Former member".
                            </p>
                            <Input
                                aria-label="Your password"
                                type="password"
                                placeholder="Your password"
                                value={account.data.password}
                                onChange={(e) =>
                                    account.setData('password', e.target.value)
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
                                    !account.data.password || account.processing
                                }
                                onClick={() =>
                                    account.delete(`${settingsBase}/profile`)
                                }
                            >
                                Delete my account
                            </Button>
                        </>
                    )}
                </div>
            </Section>

            <Button
                variant="outline"
                onClick={() => router.post('/logout')}
                className="mt-4"
            >
                Sign out
                <LogOutIcon />
            </Button>

            <BottomNav householdId={household.id} active="settings" />

            <AlertDialog
                open={isConfirmingDeleteHousehold}
                onOpenChange={setIsConfirmingDeleteHousehold}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Are you absolutely sure?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This action cannot be undone. This will permanently
                            delete the{' '}
                            <strong className="font-semibold text-foreground">
                                {household.name}
                            </strong>{' '}
                            household along with all associated budgets,
                            transactions, and member data.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={del.processing}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={del.processing}
                            onClick={handleDeleteHousehold}
                        >
                            {del.processing ? (
                                <div className="flex items-center gap-2">
                                    <Spinner /> Deleting...
                                </div>
                            ) : (
                                'Permanently Delete Household'
                            )}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
