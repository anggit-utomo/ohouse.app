export type Currency = {
    code: string;
    symbol: string;
    exponent: number;
    locale: string;
};

let current: Currency = {
    code: 'IDR',
    symbol: 'Rp',
    exponent: 0,
    locale: 'id-ID',
};

export const setCurrency = (currency?: Currency | null) => {
    if (currency) current = currency;
};

export const currency = () => current;

/** The number only, with the right separators and decimals: 123456 becomes "1.234,56" for a 2-digit currency. */
export const plain = (minor: number) =>
    new Intl.NumberFormat(current.locale, {
        minimumFractionDigits: current.exponent,
        maximumFractionDigits: current.exponent,
    }).format(minor / 10 ** current.exponent);

/** With the symbol. Named rupiah for history; it formats in whatever currency the household uses. */
export const rupiah = (minor: number) => `${current.symbol} ${plain(minor)}`;
export const money = rupiah;
