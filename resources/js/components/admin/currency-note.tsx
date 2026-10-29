import { TextLink } from '@/components/ui/text-link';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/** Hint for money inputs: the active currency, or a prompt to set one. */
export function useCurrencyHint(): string {
    const { currency } = usePage<SharedData>().props;

    return currency ? `In ${currency.code}.` : 'No site currency set yet.';
}

export function CurrencyMissingNotice() {
    const { currency } = usePage<SharedData>().props;

    if (currency) {
        return null;
    }

    return (
        <p
            className="rounded-control border border-warning-600/30 bg-surface p-3 text-sm text-warning-600"
            role="alert"
        >
            Choose the site currency in <TextLink href={route('admin.settings.edit')}>Settings</TextLink> before
            publishing prices.
        </p>
    );
}
