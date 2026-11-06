import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/** Base UI selects emit null when cleared; ignore that instead of storing it. */
export function setIfNotNull(
    setter: (value: string) => void,
): (value: string | null) => void {
    return (value) => {
        if (value !== null) {
            setter(value);
        }
    };
}
