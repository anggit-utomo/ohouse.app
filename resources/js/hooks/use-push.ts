import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

export type PushState =
    | 'loading'
    | 'unsupported'
    | 'needs-install'
    | 'denied'
    | 'off'
    | 'on';

const isIos = () =>
    /iphone|ipad|ipod/i.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isStandalone = () =>
    window.matchMedia('(display-mode: standalone)').matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true;

function toKey(base64: string) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4))
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

/** The service worker, or null if it never becomes ready (for example in development). */
const worker = (ms = 4000) =>
    Promise.race([
        navigator.serviceWorker.ready,
        new Promise<null>((resolve) => setTimeout(() => resolve(null), ms)),
    ]);

export function usePush(publicKey: string | null) {
    const [state, setState] = useState<PushState>('loading');
    const [endpoint, setEndpoint] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const refresh = useCallback(async () => {
        try {
            if (isIos() && !isStandalone()) return setState('needs-install');
            if (
                !publicKey ||
                !('serviceWorker' in navigator) ||
                !('PushManager' in window) ||
                !('Notification' in window)
            )
                return setState('unsupported');
            if (Notification.permission === 'denied') return setState('denied');

            const registration = await worker();
            if (!registration) return setState('unsupported');

            const subscription =
                await registration.pushManager.getSubscription();
            setEndpoint(subscription?.endpoint ?? null);
            setState(
                subscription && Notification.permission === 'granted'
                    ? 'on'
                    : 'off',
            );
        } catch {
            setState('unsupported');
        }
    }, [publicKey]);

    useEffect(() => {
        void refresh();
    }, [refresh]);

    const enable = async () => {
        if (!publicKey) return;
        setBusy(true);

        try {
            // this has to be the first thing that happens after the tap, or iPhone refuses to ask
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return await refresh();

            const registration = await worker();
            if (!registration) throw new Error('no worker');

            const subscription =
                (await registration.pushManager.getSubscription()) ??
                (await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: toKey(publicKey),
                }));

            const json = subscription.toJSON();
            const encodings = (
                window.PushManager as unknown as {
                    supportedContentEncodings?: string[];
                }
            ).supportedContentEncodings;

            router.post(
                '/push/subscribe',
                {
                    endpoint: json.endpoint,
                    keys: json.keys,
                    contentEncoding: encodings?.[0] ?? 'aesgcm',
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onFinish: () => void refresh(),
                },
            );
        } catch {
            toast.error('Could not turn on notifications on this device.');
        } finally {
            setBusy(false);
        }
    };

    const disable = async () => {
        setBusy(true);

        try {
            const registration = await worker();
            const subscription =
                await registration?.pushManager.getSubscription();

            if (subscription) {
                const current = subscription.endpoint;
                await subscription.unsubscribe();
                router.delete('/push/subscribe', {
                    data: { endpoint: current },
                    preserveScroll: true,
                    preserveState: true,
                });
            }
        } finally {
            setBusy(false);
            void refresh();
        }
    };

    return { state, endpoint, busy, enable, disable };
}

export async function diagnose(publicKey: string | null) {
    const registrations =
        'serviceWorker' in navigator
            ? await navigator.serviceWorker.getRegistrations()
            : [];

    return {
        publicKeySet: Boolean(publicKey),
        secureContext: window.isSecureContext,
        standalone: isStandalone(),
        ios: isIos(),
        serviceWorkerApi: 'serviceWorker' in navigator,
        pushManagerApi: 'PushManager' in window,
        notificationApi: 'Notification' in window,
        permission: 'Notification' in window ? Notification.permission : 'n/a',
        workerController: Boolean(navigator.serviceWorker?.controller),
        registrations:
            registrations
                .map(
                    (r) =>
                        `${r.active ? 'active' : r.installing ? 'installing' : r.waiting ? 'waiting' : 'none'} ${r.scope}`,
                )
                .join(', ') || 'none',
    };
}
