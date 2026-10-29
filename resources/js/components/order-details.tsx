import { DetailRow } from '@/components/catalogue/detail-row';
import { Price } from '@/components/catalogue/price';
import { formatDate, formatSlot } from '@/lib/format';
import type { Order } from '@/types';
import { FileText } from 'lucide-react';

function addressText(address: Order['address']): string {
    return [
        address.line1,
        address.line2,
        [address.city, address.region, address.postcode].filter(Boolean).join(', '),
        address.country_name,
    ]
        .filter(Boolean)
        .join(' · ');
}

interface OrderDetailsProps {
    order: Order;
    /** Link to download the prescription, when the viewer may. */
    prescriptionUrl?: string;
}

/** Slot, address, payment and what was booked for whom (customer and admin views). */
export function OrderDetails({ order, prescriptionUrl }: OrderDetailsProps) {
    return (
        <div className="space-y-6">
            <dl className="divide-y divide-border rounded-card border border-border bg-surface px-4 md:px-6">
                <DetailRow label="Collection">
                    {formatSlot(order.slot_starts_at, order.slot_ends_at, order.timezone)}
                </DetailRow>
                <DetailRow label="Address">
                    {order.address.label && <span className="font-medium">{order.address.label}: </span>}
                    {addressText(order.address)}
                </DetailRow>
                <DetailRow label="Payment">{order.payment_method_label}</DetailRow>
                {order.has_prescription && (
                    <DetailRow label="Prescription">
                        {prescriptionUrl ? (
                            <a
                                href={prescriptionUrl}
                                className="inline-flex items-center gap-1.5 font-medium text-primary-700 hover:underline"
                            >
                                <FileText className="size-4" aria-hidden="true" />
                                {order.prescription_name ?? 'Download'}
                            </a>
                        ) : (
                            order.prescription_name
                        )}
                    </DetailRow>
                )}
            </dl>

            <section aria-labelledby="booked-heading">
                <h2 id="booked-heading" className="text-lg font-semibold">
                    Tests booked
                </h2>
                <ul className="mt-3 space-y-3">
                    {order.patients?.map((patient) => (
                        <li
                            key={`${patient.name}-${patient.date_of_birth}`}
                            className="rounded-card border border-border bg-surface"
                        >
                            <p className="border-b border-border px-4 py-3 text-sm">
                                <span className="font-medium">{patient.name}</span>
                                <span className="text-muted">
                                    {' '}
                                    · born{' '}
                                    {formatDate(patient.date_of_birth, {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </span>
                            </p>
                            <ul className="divide-y divide-border">
                                {patient.items.map((item, index) => (
                                    <li key={index} className="flex items-start justify-between gap-4 px-4 py-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">{item.name}</p>
                                            {item.test_names && (
                                                <p className="text-sm text-muted">{item.test_names.join(', ')}</p>
                                            )}
                                        </div>
                                        <Price
                                            amount={item.price_amount}
                                            currencyCode={order.currency}
                                            className="shrink-0 text-sm"
                                        />
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>

                <div className="mt-4 flex items-baseline justify-between rounded-card border border-border bg-surface p-4">
                    <span className="font-semibold">Total</span>
                    <Price amount={order.total_amount} currencyCode={order.currency} className="text-lg" />
                </div>
            </section>
        </div>
    );
}
