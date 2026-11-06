import type { Currency } from '@/lib/money';
import type { Auth } from '@/types/auth';

export type Flash = {
    success: string | null;
    error: string | null;
};

export type SharedProps = {
    name: string;
    auth: Auth;
    flash: Flash;
    currency: Currency;
};
