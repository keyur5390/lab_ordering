import { addressLines } from '@/components/address-lines';
import { Price } from '@/components/catalogue/price';
import { OrderStatusBadge } from '@/components/order-status-badge';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatTime } from '@/lib/format';
import type { Address, Option, OrderStatus, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, MapPin, Phone } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Visit {
    number: string;
    status: OrderStatus;
    status_label: string;
    slot_starts_at: string;
    slot_ends_at: string;
    area: string | null;
    patients_count: number;
    // Only for the collector's own visits.
    address?: Pick<Address, 'label' | 'line1' | 'line2' | 'city' | 'region' | 'postcode' | 'country'>;
    map_url?: string;
    phone?: string | null;
    patients?: { name: string; tests: string[] }[];
    pay_at_collection?: boolean;
    total_amount?: number;
    currency?: string;
    payment_collected?: boolean;
    visit_failed?: boolean;
    visit_failure?: string | null;
}

interface CollectorProps {
    date: string;
    today: string;
    timezone: string;
    visits: Visit[];
    claimable: Visit[];
    hasAreas: boolean;
    failureReasons: Option[];
    paymentMethods: Option[];
}

/** Adds days to a YYYY-MM-DD date. */
function shift(date: string, days: number): string {
    const d = new Date(`${date}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);

    return d.toISOString().slice(0, 10);
}

function slot(visit: Visit, timezone: string): string {
    return `${formatTime(visit.slot_starts_at, timezone)}–${formatTime(visit.slot_ends_at, timezone)}`;
}

const actionClass =
    'inline-flex h-11 items-center gap-2 rounded-control border border-border bg-surface px-3 text-sm font-medium hover:bg-canvas';

function FailedForm({ visit, reasons, onDone }: { visit: Visit; reasons: Option[]; onDone: () => void }) {
    const { data, setData, post, processing, errors } = useForm({ reason: '', note: '' });
    const id = `failed-${visit.number}`;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('collector.failed', visit.number), { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="space-y-3 rounded-control border border-border p-3">
            <div className="space-y-1.5">
                <Label htmlFor={`${id}-reason`}>Why couldn't you collect?</Label>
                <Select id={`${id}-reason`} value={data.reason} onChange={(e) => setData('reason', e.target.value)}>
                    <option value="" disabled>
                        Choose a reason
                    </option>
                    {reasons.map((reason) => (
                        <option key={reason.value} value={reason.value}>
                            {reason.label}
                        </option>
                    ))}
                </Select>
                <InputError message={errors.reason} />
            </div>
            <div className="space-y-1.5">
                <Label htmlFor={`${id}-note`}>Note {data.reason !== 'other' && '(optional)'}</Label>
                <Textarea
                    id={`${id}-note`}
                    rows={2}
                    value={data.note}
                    onChange={(e) => setData('note', e.target.value)}
                />
                <InputError message={errors.note} />
            </div>
            <div className="flex gap-2">
                <Button type="submit" variant="danger" processing={processing}>
                    Report failed visit
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Back
                </Button>
            </div>
        </form>
    );
}

function PaymentForm({ visit, methods }: { visit: Visit; methods: Option[] }) {
    const { data, setData, post, processing, errors } = useForm({ method: '' });
    const id = `payment-${visit.number}`;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('collector.payment', visit.number), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            <div className="space-y-1.5">
                <Label htmlFor={id}>
                    Collect <Price amount={visit.total_amount ?? 0} currencyCode={visit.currency} />
                </Label>
                <Select
                    id={id}
                    className="w-40"
                    value={data.method}
                    onChange={(e) => setData('method', e.target.value)}
                >
                    <option value="" disabled>
                        Paid by…
                    </option>
                    {methods.map((method) => (
                        <option key={method.value} value={method.value}>
                            {method.label}
                        </option>
                    ))}
                </Select>
            </div>
            <Button type="submit" variant="secondary" processing={processing} disabled={!data.method}>
                Payment collected
            </Button>
            <InputError message={errors.method} className="w-full" />
        </form>
    );
}

function MyVisit({
    visit,
    timezone,
    reasons,
    methods,
}: {
    visit: Visit;
    timezone: string;
    reasons: Option[];
    methods: Option[];
}) {
    const [failing, setFailing] = useState(false);
    const [processing, setProcessing] = useState(false);
    const toDo = visit.status === 'collector_assigned';

    return (
        <li className="space-y-3 rounded-card border border-border bg-surface p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-lg font-semibold tabular-nums">{slot(visit, timezone)}</p>
                <OrderStatusBadge status={visit.status} label={visit.status_label} />
            </div>
            <p className="text-sm text-muted">{visit.number}</p>

            {visit.visit_failed && <Alert tone="warning">Visit reported as failed: {visit.visit_failure}.</Alert>}

            {visit.address && (
                <p className="text-sm">
                    {visit.address.label && <span className="font-medium">{visit.address.label}: </span>}
                    {addressLines(visit.address).join(', ')}
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                {visit.map_url && (
                    <a href={visit.map_url} target="_blank" rel="noreferrer" className={actionClass}>
                        <MapPin className="size-4" aria-hidden="true" />
                        Map
                    </a>
                )}
                {visit.phone && (
                    <a href={`tel:${visit.phone.replace(/[\s-]/g, '')}`} className={actionClass}>
                        <Phone className="size-4" aria-hidden="true" />
                        Call {visit.phone}
                    </a>
                )}
            </div>

            <ul className="space-y-1 text-sm">
                {visit.patients?.map((patient) => (
                    <li key={patient.name}>
                        <span className="font-medium">{patient.name}</span>
                        <span className="text-muted">: {patient.tests.join(', ')}</span>
                    </li>
                ))}
            </ul>

            {visit.pay_at_collection &&
                (visit.payment_collected ? (
                    <Badge tone="success">Payment collected</Badge>
                ) : (
                    <PaymentForm visit={visit} methods={methods} />
                ))}

            {toDo &&
                (failing ? (
                    <FailedForm visit={visit} reasons={reasons} onDone={() => setFailing(false)} />
                ) : (
                    <div className="flex flex-wrap gap-2 border-t border-border pt-3">
                        <Button
                            processing={processing}
                            onClick={() =>
                                router.post(
                                    route('collector.collected', visit.number),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onStart: () => setProcessing(true),
                                        onFinish: () => setProcessing(false),
                                    },
                                )
                            }
                        >
                            Sample collected
                        </Button>
                        <Button variant="ghost" className="text-danger-600" onClick={() => setFailing(true)}>
                            Couldn't collect
                        </Button>
                    </div>
                ))}
        </li>
    );
}

export default function CollectorVisits({
    date,
    today,
    timezone,
    visits,
    claimable,
    hasAreas,
    failureReasons,
    paymentMethods,
}: CollectorProps) {
    const { status, errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [claiming, setClaiming] = useState<string | null>(null);
    const dayLink = (day: string) => route('collector.index', day === today ? {} : { date: day });

    return (
        <AppLayout>
            <Head title="My visits" />

            <section className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12">
                <h1 className="text-2xl font-semibold md:text-3xl">My visits</h1>

                <nav aria-label="Day" className="mt-4 flex items-center gap-2">
                    <Link href={dayLink(shift(date, -1))} className={actionClass} aria-label="Previous day">
                        <ChevronLeft className="size-4" aria-hidden="true" />
                    </Link>
                    <p className="min-w-0 flex-1 text-center font-medium">
                        {date === today ? 'Today, ' : ''}
                        {formatDate(date, { weekday: 'long', day: 'numeric', month: 'long' })}
                    </p>
                    <Link href={dayLink(shift(date, 1))} className={actionClass} aria-label="Next day">
                        <ChevronRight className="size-4" aria-hidden="true" />
                    </Link>
                </nav>
                {date !== today && (
                    <p className="mt-2 text-center text-sm">
                        <Link href={dayLink(today)} className="font-medium text-primary-700 hover:underline">
                            Back to today
                        </Link>
                    </p>
                )}

                {status && (
                    <Alert tone="success" className="mt-4">
                        {status}
                    </Alert>
                )}
                {(errors.order || errors.status) && (
                    <Alert tone="danger" className="mt-4">
                        {errors.order ?? errors.status}
                    </Alert>
                )}

                <h2 className="mt-6 text-lg font-semibold">Your visits ({visits.length})</h2>
                {visits.length === 0 ? (
                    <p className="mt-2 text-sm text-muted">No visits for you on this day.</p>
                ) : (
                    <ul className="mt-3 space-y-3">
                        {visits.map((visit) => (
                            <MyVisit
                                key={visit.number}
                                visit={visit}
                                timezone={timezone}
                                reasons={failureReasons}
                                methods={paymentMethods}
                            />
                        ))}
                    </ul>
                )}

                <h2 className="mt-8 text-lg font-semibold">Available to take</h2>
                {!hasAreas ? (
                    <p className="mt-2 text-sm text-muted">You haven't been given any areas yet. Ask the office.</p>
                ) : claimable.length === 0 ? (
                    <p className="mt-2 text-sm text-muted">No unassigned visits in your areas on this day.</p>
                ) : (
                    <ul className="mt-3 divide-y divide-border rounded-card border border-border bg-surface">
                        {claimable.map((visit) => (
                            <li key={visit.number} className="flex flex-wrap items-center justify-between gap-3 p-4">
                                <div>
                                    <p className="font-medium tabular-nums">{slot(visit, timezone)}</p>
                                    <p className="text-sm text-muted">
                                        {visit.area} · {visit.patients_count}{' '}
                                        {visit.patients_count === 1 ? 'patient' : 'patients'}
                                    </p>
                                </div>
                                <Button
                                    variant="secondary"
                                    processing={claiming === visit.number}
                                    onClick={() =>
                                        router.post(
                                            route('collector.claim', visit.number),
                                            {},
                                            {
                                                preserveScroll: true,
                                                onStart: () => setClaiming(visit.number),
                                                onFinish: () => setClaiming(null),
                                            },
                                        )
                                    }
                                >
                                    Take this visit
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}
