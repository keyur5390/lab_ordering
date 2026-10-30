import { DetailRow } from '@/components/catalogue/detail-row';
import { Price } from '@/components/catalogue/price';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatSlot } from '@/lib/format';
import type { Order } from '@/types';
import { FileText } from 'lucide-react';

const refundTones = { pending: 'warning', succeeded: 'success', failed: 'danger' } as const;

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
                <DetailRow label={order.collection_type === 'lab_visit' ? 'Lab visit' : 'Collection'}>
                    {formatSlot(order.slot_starts_at, order.slot_ends_at, order.timezone)}
                </DetailRow>
                {order.collector_first_name && <DetailRow label="Collector">{order.collector_first_name}</DetailRow>}
                <DetailRow label={order.collection_type === 'lab_visit' ? 'Branch' : 'Address'}>
                    {order.address.label && <span className="font-medium">{order.address.label}: </span>}
                    {addressText(order.address)}
                    {order.branch && (
                        <span className="mt-1 block space-y-1 text-muted">
                            {order.branch.directions && <span className="block">{order.branch.directions}</span>}
                            <span className="flex flex-wrap gap-x-4">
                                <a
                                    href={order.branch.map_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="font-medium text-primary-700 hover:underline"
                                >
                                    Map
                                </a>
                                {order.branch.phone && (
                                    <a
                                        href={`tel:${order.branch.phone.replace(/[\s-]/g, '')}`}
                                        className="font-medium text-primary-700 hover:underline"
                                    >
                                        {order.branch.phone}
                                    </a>
                                )}
                            </span>
                        </span>
                    )}
                </DetailRow>
                <DetailRow label="Payment">
                    {order.payment_method_label}
                    {order.payment && order.payment.paid > 0 && (
                        <>
                            {' · paid '}
                            <Price amount={order.payment.paid} currencyCode={order.currency} />
                        </>
                    )}
                    {order.payment && order.payment.refunded > 0 && (
                        <>
                            {' · refunded '}
                            <Price amount={order.payment.refunded} currencyCode={order.currency} />
                        </>
                    )}
                </DetailRow>
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
                                {patient.items.map((item) => (
                                    <li key={item.id} className="flex items-start justify-between gap-4 px-4 py-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">{item.name}</p>
                                            {item.test_names && (
                                                <p className="text-sm text-muted">{item.test_names.join(', ')}</p>
                                            )}
                                            {item.report_url && (
                                                <a
                                                    href={item.report_url}
                                                    className="mt-1 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-primary-700 hover:underline sm:min-h-0"
                                                >
                                                    <FileText className="size-4" aria-hidden="true" />
                                                    Download report
                                                </a>
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

                <dl className="mt-4 space-y-1.5 rounded-card border border-border bg-surface p-4 text-sm">
                    <div className="flex justify-between gap-3">
                        <dt>Tests</dt>
                        <dd>
                            <Price amount={order.subtotal_amount} currencyCode={order.currency} />
                        </dd>
                    </div>
                    {order.discount_amount > 0 && (
                        <div className="flex justify-between gap-3">
                            <dt>Code {order.coupon_code}</dt>
                            <dd>
                                −<Price amount={order.discount_amount} currencyCode={order.currency} />
                            </dd>
                        </div>
                    )}
                    {order.collection_type === 'home' && (
                        <div className="flex justify-between gap-3">
                            <dt>Home collection</dt>
                            <dd>
                                {order.collection_fee_amount > 0 ? (
                                    <Price amount={order.collection_fee_amount} currencyCode={order.currency} />
                                ) : (
                                    'Free'
                                )}
                            </dd>
                        </div>
                    )}
                    {order.tax_rate_percent !== null && !order.tax_inclusive && (
                        <div className="flex justify-between gap-3">
                            <dt>Tax ({order.tax_rate_percent}%)</dt>
                            <dd>
                                <Price amount={order.tax_amount} currencyCode={order.currency} />
                            </dd>
                        </div>
                    )}
                    <div className="flex items-baseline justify-between gap-3 border-t border-border pt-3">
                        <dt className="text-base font-semibold">Total</dt>
                        <dd>
                            <Price amount={order.total_amount} currencyCode={order.currency} className="text-lg" />
                        </dd>
                    </div>
                    {order.tax_rate_percent !== null && order.tax_inclusive && (
                        <p className="text-xs text-muted">
                            Includes <Price amount={order.tax_amount} currencyCode={order.currency} /> tax (
                            {order.tax_rate_percent}%).
                        </p>
                    )}
                </dl>
            </section>

            {order.payment && order.payment.refunds.length > 0 && (
                <section aria-labelledby="refunds-heading">
                    <h2 id="refunds-heading" className="text-lg font-semibold">
                        Refunds
                    </h2>
                    <ul className="mt-3 divide-y divide-border rounded-card border border-border bg-surface">
                        {order.payment.refunds.map((refund) => (
                            <li key={refund.id} className="flex items-start justify-between gap-4 px-4 py-3">
                                <div className="min-w-0 text-sm">
                                    <p className="flex flex-wrap items-center gap-2">
                                        <span>
                                            {formatDate(refund.created_at.slice(0, 10), {
                                                day: 'numeric',
                                                month: 'short',
                                                year: 'numeric',
                                            })}
                                        </span>
                                        <Badge tone={refundTones[refund.status]}>{refund.status_label}</Badge>
                                    </p>
                                    {(refund.reason || refund.by) && (
                                        <p className="text-muted">
                                            {[refund.reason, refund.by && `by ${refund.by}`]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    )}
                                </div>
                                <Price
                                    amount={refund.amount}
                                    currencyCode={order.currency}
                                    className="shrink-0 text-sm"
                                />
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
