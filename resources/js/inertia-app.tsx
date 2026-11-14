import type { Page } from '@inertiajs/core';
import type { ReactElement } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import { setCurrency } from '@/lib/money';
import type { SharedProps } from '@/types/shared';

export const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

export const pageTitle = (title: string): string =>
    title ? `${title} - ${appName}` : appName;

export const resolveLayout = (name: string) => {
    switch (true) {
        case name === 'welcome':
            return null;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout];
        default:
            return AppLayout;
    }
};

export function withApp(
    app: ReactElement,
    { page }: { ssr: boolean; page: Page<SharedProps> },
): ReactElement {
    setCurrency(page.props.currency);

    return (
        <TooltipProvider delay={0}>
            {app}
            <Toaster position="top-right" richColors />
        </TooltipProvider>
    );
}
