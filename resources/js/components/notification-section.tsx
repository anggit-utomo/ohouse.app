import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { usePush } from '@/hooks/use-push';

type Device = { id: number; device: string; endpoint: string; added: string };

type Props = {
    base: string;
    publicKey: string | null;
    devices: Device[];
    notify: { entries: boolean; amounts: boolean };
};

const blocked = {
    'needs-install':
        'On iPhone, notifications only work from the app on your Home Screen. Tap Share, then Add to Home Screen, open it from its icon, and come back here.',
    unsupported: "This browser can't receive notifications.",
    denied: 'Notifications are blocked. Allow them in your phone settings: Settings, then Notifications on iPhone, or the site settings in Chrome on Android.',
} as const;

export function NotificationsSection({
    base,
    publicKey,
    devices,
    notify,
}: Props) {
    const { state, endpoint, busy, enable, disable } = usePush(publicKey);

    const save = (
        patch: Partial<{ notify: boolean; notify_amounts: boolean }>,
    ) =>
        router.patch(
            `${base}/settings/notifications`,
            {
                notify: notify.entries,
                notify_amounts: notify.amounts,
                ...patch,
            },
            { preserveScroll: true },
        );

    return (
        <section className="grid gap-3 rounded-xl border p-4">
            <div>
                <h2 className="font-semibold">Notifications</h2>
                <p className="text-sm text-muted-foreground">
                    Hear when someone else adds an expense, income or move.
                    Messages are encrypted; Apple and Google only deliver them.
                </p>
            </div>

            {state in blocked && (
                <p className="rounded-lg bg-muted p-3 text-sm">
                    {blocked[state as keyof typeof blocked]}
                </p>
            )}

            {state === 'off' && (
                <Button disabled={busy} onClick={enable}>
                    Turn on notifications on this device
                </Button>
            )}

            {state === 'on' && (
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        className="flex-1"
                        disabled={busy}
                        onClick={() =>
                            router.post(
                                '/push/test',
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Send a test
                    </Button>
                    <Button
                        variant="outline"
                        className="flex-1"
                        disabled={busy}
                        onClick={disable}
                    >
                        Turn off here
                    </Button>
                </div>
            )}

            <label className="flex items-center justify-between gap-3 text-sm">
                Notify me about this household
                <Switch
                    checked={notify.entries}
                    onCheckedChange={(v) => save({ notify: v })}
                />
            </label>
            <label className="flex items-center justify-between gap-3 text-sm">
                Show amounts in notifications
                <Switch
                    checked={notify.amounts}
                    onCheckedChange={(v) => save({ notify_amounts: v })}
                />
            </label>

            {devices.length > 0 && (
                <div className="grid gap-1">
                    <p className="text-sm font-medium">Your devices</p>
                    {devices.map((d) => (
                        <div
                            key={d.id}
                            className="flex items-center justify-between text-sm"
                        >
                            <span>
                                {d.device}
                                {d.endpoint === endpoint && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        (this device)
                                    </span>
                                )}
                            </span>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Remove ${d.device}`}
                                onClick={() =>
                                    d.endpoint === endpoint
                                        ? disable()
                                        : router.delete(
                                              `/push/devices/${d.id}`,
                                              { preserveScroll: true },
                                          )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
