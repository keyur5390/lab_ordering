import { addressLines } from '@/components/address-lines';
import { DeleteButton } from '@/components/delete-button';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { Address, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';

const primaryLinkClass =
    'inline-flex h-11 items-center justify-center rounded-control bg-primary-600 px-4 text-sm font-medium whitespace-nowrap text-white hover:bg-primary-700';

export default function AddressesIndex({ addresses }: { addresses: Address[] }) {
    const { status } = usePage<SharedData>().props;

    return (
        <AppLayout>
            <Head title="Addresses" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('account')} className="text-sm">
                    ← Your account
                </TextLink>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold md:text-3xl">Addresses</h1>
                        <p className="mt-1 text-sm text-muted">Where we can collect samples from you.</p>
                    </div>
                    <Link href={route('addresses.create')} className={primaryLinkClass}>
                        Add address
                    </Link>
                </div>

                {status && (
                    <Alert tone="success" className="mt-4">
                        {status}
                    </Alert>
                )}

                {addresses.length === 0 ? (
                    <div className="mt-6 flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center">
                        <MapPin className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                        <p className="mt-3 text-sm text-muted">No addresses yet.</p>
                        <Link href={route('addresses.create')} className={`${primaryLinkClass} mt-4`}>
                            Add your first address
                        </Link>
                    </div>
                ) : (
                    <ul className="mt-6 divide-y divide-border rounded-card border border-border bg-surface">
                        {addresses.map((address) => (
                            <li key={address.id} className="flex items-start justify-between gap-3 p-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        {address.label && <span className="font-medium">{address.label}</span>}
                                        {address.serviceable ? (
                                            <Badge tone="success">Home collection available</Badge>
                                        ) : (
                                            <Badge tone="warning">Outside our collection area</Badge>
                                        )}
                                    </div>
                                    <address className="mt-1 text-sm text-muted not-italic">
                                        {addressLines(address).map((line, index) => (
                                            <span key={index} className="block">
                                                {line}
                                            </span>
                                        ))}
                                    </address>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Link
                                        href={route('addresses.edit', address.id)}
                                        className="inline-flex h-11 items-center rounded-control px-3 text-sm font-medium text-primary-700 hover:bg-canvas sm:h-9"
                                        aria-label={`Edit ${address.label ?? address.line1}`}
                                    >
                                        Edit
                                    </Link>
                                    <DeleteButton
                                        href={route('addresses.destroy', address.id)}
                                        itemName={address.label ?? address.line1}
                                        itemType="address"
                                        verb="Remove"
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
