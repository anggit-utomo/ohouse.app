import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { toast } from 'sonner';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

type Flash = { success?: string | null; error?: string | null };

// a message the server flashed after an action
router.on('success', (event) => {
    const flash = (event.detail.page.props as { flash?: Flash }).flash;

    if (flash?.success) toast.success(flash.success, { id: flash.success });
    if (flash?.error) toast.error(flash.error);
});

// validation errors and refusals, such as "this period is closed"
router.on('error', (event) => {
    const first = Object.values(event.detail.errors)[0];

    if (first) toast.error(String(first));
});

// server errors, expired sessions, rate limits
router.on('invalid', (event) => {
    event.preventDefault(); // no raw error page in a pop-up

    const messages: Record<number, string> = {
        403: "You don't have permission to do that.",
        404: 'That page was not found.',
        419: 'Your session expired. Please reload the page.',
        429: 'Too many tries. Wait a minute and try again.',
    };

    toast.error(
        messages[event.detail.response.status] ??
            'Something went wrong. Please try again.',
    );
});

// no connection
router.on('exception', () =>
    toast.error('Could not reach the server. Check your connection.'),
);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster position="top-center" />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () =>
        navigator.serviceWorker.register('/sw.js'),
    );
}
