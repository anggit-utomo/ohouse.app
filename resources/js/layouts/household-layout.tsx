import type { PropsWithChildren } from 'react';

export default function HouseholdLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-dvh bg-background pt-[env(safe-area-inset-top)] text-foreground">
            <main className="mx-auto w-full max-w-md">{children}</main>
        </div>
    );
}
