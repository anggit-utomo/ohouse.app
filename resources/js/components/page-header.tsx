import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = { title: string; href: string; action?: ReactNode };

export function PageHeader({ title, href, action }: Props) {
    return (
        <div className="flex items-center justify-between gap-3">
            <Link
                href={href}
                aria-label={`Back from ${title}`}
                className="-ml-2 flex items-center gap-1 rounded-md p-2 text-lg font-semibold"
            >
                <ChevronLeft className="size-5" />
                {title}
            </Link>
            {action}
        </div>
    );
}
