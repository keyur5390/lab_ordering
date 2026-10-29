import type { Currency } from '@/types';

/** Format an amount in minor units (e.g. 49950) for display, e.g. "₹499.50". */
export function formatMoney(amount: number, currency: Currency): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.code,
        minimumFractionDigits: currency.decimals,
        maximumFractionDigits: currency.decimals,
    }).format(amount / 10 ** currency.decimals);
}
