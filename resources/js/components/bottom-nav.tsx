import { Link } from '@inertiajs/react';
import { History, Home, Plus, Users } from 'lucide-react';

type Props = { householdId: number; active: 'home' | 'history' | 'family' };

export function BottomNav({ householdId, active }: Props) {
    const base = `/h/${householdId}`;

    const item = (
        key: Props['active'],
        href: string,
        Icon: typeof Home,
        label: string,
    ) => (
        <Link
            key={key}
            href={href}
            className={`flex flex-1 flex-col items-center gap-0.5 py-2.5 text-xs ${active === key ? 'font-semibold text-foreground' : 'text-muted-foreground'}`}
        >
            <Icon className="size-5" />
            {label}
        </Link>
    );

    return (
        <nav className="fixed inset-x-0 bottom-0 z-10 border-t bg-background pb-[env(safe-area-inset-bottom)]">
            <div className="mx-auto flex max-w-md items-center">
                {item('home', base, Home, 'Home')}
                <Link
                    href={`${base}/transactions/create`}
                    aria-label="Add transaction"
                    className="mx-2 flex size-12 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
                >
                    <Plus className="size-6" />
                </Link>
                {item('history', `${base}/transactions`, History, 'History')}
                {item('family', `${base}/family`, Users, 'Family')}
            </div>
        </nav>
    );
}
