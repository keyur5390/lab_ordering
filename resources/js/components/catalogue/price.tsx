import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface PriceProps {
    /** What the customer pays, in minor units. */
    amount: number;
    /** Price before discount, in minor units; struck through when higher than `amount` (DESIGN.md §6). */
    originalAmount?: number;
    className?: string;
    /** Currency the amount is in, e.g. an order's; defaults to the site currency. */
    currencyCode?: string;
}

/** Formatted price in the site currency. Renders nothing until an admin sets the currency. */
export function Price({ amount, originalAmount, className, currencyCode }: PriceProps) {
    const { currency: siteCurrency } = usePage<SharedData>().props;
    // All supported currencies have 2 decimals (App\Enums\Currency).
    const currency = currencyCode ? { code: currencyCode, decimals: siteCurrency?.decimals ?? 2 } : siteCurrency;

    if (!currency) {
        return null;
    }

    const discounted = originalAmount !== undefined && originalAmount > amount;

    return (
        <span className={cn('inline-flex flex-wrap items-baseline gap-x-2', className)}>
            <span className="font-semibold tabular-nums">{formatMoney(amount, currency)}</span>
            {discounted && (
                <s className="text-sm text-muted tabular-nums">
                    <span className="sr-only">Was </span>
                    {formatMoney(originalAmount, currency)}
                </s>
            )}
        </span>
    );
}
