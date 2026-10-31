import type { Address } from '@/types';

/** An address as display lines, e.g. ["12 Park Road", "Flat 4", "London, SW1A 1AA", "United Kingdom"]. */
export function addressLines(
    address: Pick<Address, 'line1' | 'line2' | 'city' | 'region' | 'postcode'> & { country_name?: string | null },
): string[] {
    return [
        address.line1,
        address.line2,
        [address.city, address.region, address.postcode].filter(Boolean).join(', '),
        address.country_name,
    ].filter((line): line is string => Boolean(line));
}
